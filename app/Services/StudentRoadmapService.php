<?php

namespace App\Services;

use App\Enums\Skill;
use App\Models\CardProgress;
use App\Models\Classroom;
use App\Models\Deck;
use App\Models\Document;
use App\Models\DocumentView;
use App\Models\Mission;
use App\Models\SessionAttendance;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * Dựng "lộ trình" (roadmap) trang Lớp của em cho một học sinh:
 * danh sách buổi (chỉ buổi cô cho hiện) + nội dung từng buổi kèm trạng thái riêng của em.
 */
class StudentRoadmapService
{
    public function __construct(private readonly ClassroomStatsService $classStats) {}

    /** Danh sách lớp học sinh đang tham gia. */
    public function myClassrooms(User $student): array
    {
        return $student->classes()
            ->with('teacher:id,name')
            ->get()
            ->map(function (Classroom $c) use ($student) {
                $missions = Mission::where('classroom_id', $c->id)->where('user_id', $student->id);
                $total = (clone $missions)->count();
                $done = (clone $missions)->where('status', 'done')->count();

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'cover_url' => $c->cover_url,
                    'teacher_name' => $c->teacher?->name,
                    'students_count' => $c->students()->count(),
                    'starts_on' => $c->starts_on?->toDateString(),
                    'ends_on' => $c->ends_on?->toDateString(),
                    'status' => $c->status(),
                    'my_progress_pct' => $total > 0 ? (int) round($done / $total * 100) : 0,
                ];
            })
            ->values()
            ->all();
    }

    /** Lộ trình đầy đủ của một lớp cho học sinh. */
    public function roadmap(Classroom $classroom, User $student): array
    {
        $sessions = $classroom->sessions()
            ->where('is_visible', true)
            ->orderBy('order')
            ->get();

        $sessionIds = $sessions->pluck('id');
        $today = now()->startOfDay();

        // Missions của em trong lớp (bỏ nháp + lịch chưa tới giờ), kèm nội dung.
        $missions = Mission::where('classroom_id', $classroom->id)
            ->where('user_id', $student->id)
            ->whereIn('class_session_id', $sessionIds)
            ->where('status', '!=', 'draft')
            ->where(fn ($q) => $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))
            ->with('missionable')
            ->get();

        // Nạp trước dữ liệu tiến độ để tránh N+1.
        $docIds = $missions->filter(fn ($m) => $m->missionable instanceof Document)->pluck('missionable_id');
        $deckModels = $missions->filter(fn ($m) => $m->missionable instanceof Deck)->pluck('missionable');

        // Gom theo MISSION chứ không theo test: lượt em tự luyện cùng đề đó ở Thư viện
        // (mission_id = null) không được tính là đã làm bài cô giao.
        $attempts = TestAttempt::where('user_id', $student->id)
            ->whereIn('mission_id', $missions->pluck('id'))
            ->get()
            ->groupBy('mission_id');

        $views = DocumentView::where('user_id', $student->id)
            ->whereIn('document_id', $docIds)
            ->get()
            ->keyBy('document_id');

        $deckProgress = $this->deckProgress($student, $deckModels);
        $completePct = (int) setting('content.deck_complete_pct', 80) / 100;

        $sessionsOut = $sessions->map(function ($session) use ($missions, $attempts, $views, $deckProgress, $completePct, $today) {
            $items = $missions->where('class_session_id', $session->id)
                ->sortBy(fn ($m) => $m->id)
                ->map(fn (Mission $m) => $this->buildItem($m, $attempts, $views, $deckProgress, $completePct, $today))
                ->values();

            $total = $items->count();
            $done = $items->where('done', true)->count();
            $locked = ($session->held_on && $session->held_on->gt($today)) || $total === 0;

            return [
                'id' => $session->id,
                'order' => $session->order,
                'title' => $session->title,
                'note' => $session->note,
                'is_visible' => (bool) $session->is_visible,
                'locked' => $locked,
                'progress_pct' => $total > 0 ? (int) round($done / $total * 100) : 0,
                'done' => $done,
                'total' => $total,
                'items' => $items->map(fn ($i) => Arr::except($i, ['done']))->values(),
            ];
        })->values();

        return [
            'classroom' => [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'description' => $classroom->description,
                'teacher_name' => $classroom->teacher?->name,
                'students_count' => $classroom->students()->count(),
                'starts_on' => $classroom->starts_on?->toDateString(),
                'ends_on' => $classroom->ends_on?->toDateString(),
                'status' => $classroom->status(),
            ],
            'stats' => $this->stats($classroom, $student, $sessionsOut, $sessionIds),
            'sessions' => $sessionsOut->all(),
        ];
    }

    /** @return array<int, array{known:int,total:int}> */
    private function deckProgress(User $student, $decks): array
    {
        $out = [];
        foreach ($decks as $deck) {
            $cardIds = $deck->cards()->pluck('id');
            $total = $cardIds->count();
            $known = $total > 0
                ? CardProgress::where('user_id', $student->id)->whereIn('card_id', $cardIds)->where('status', 'known')->count()
                : 0;
            $out[$deck->id] = ['known' => $known, 'total' => $total];
        }

        return $out;
    }

    private function buildItem(Mission $mission, $attempts, $views, array $deckProgress, float $completePct, Carbon $today): array
    {
        $model = $mission->missionable;
        [$type, $title, $meta] = $this->describe($model);

        $status = 'todo';
        $progress = 0;
        $done = false;
        $score = null;
        $attemptId = null;
        $attemptsUsed = 0;

        if ($model instanceof Document) {
            $view = $views->get($model->id);
            $progress = (int) ($view->progress_pct ?? 0);
            if ($view && ($view->completed_at || $progress >= 100)) {
                $status = 'viewed';
                $progress = 100;
                $done = true;
            } elseif ($progress > 0) {
                $status = 'in_progress';
            }
        } elseif ($model instanceof Deck) {
            $p = $deckProgress[$model->id] ?? ['known' => 0, 'total' => 0];
            $progress = $p['total'] > 0 ? (int) round($p['known'] / $p['total'] * 100) : 0;
            if ($p['total'] > 0 && $completePct <= $p['known'] / $p['total']) {
                $status = 'viewed';
                $done = true;
            } elseif ($p['known'] > 0) {
                $status = 'in_progress';
            }
        } elseif ($model instanceof Test) {
            $list = collect($attempts->get($mission->id) ?? []);
            $inProgress = $list->firstWhere('status', 'in_progress');
            $finished = $list->whereIn('status', ['submitted', 'pending_review', 'graded'])->sortByDesc('id')->first();
            $attemptsUsed = $list->whereIn('status', ['submitted', 'pending_review', 'graded'])->count();

            if ($inProgress) {
                $status = 'in_progress';
                $attemptId = $inProgress->id;
            } elseif ($finished) {
                $progress = 100;
                $attemptId = $finished->id;
                if ($finished->status === 'graded' || ($finished->total_score !== null)) {
                    $status = 'graded';
                    $score = (float) $finished->total_score;
                    $done = true;
                } else {
                    $status = ($type === 'writing') ? 'pending_review' : 'submitted';
                    $done = true;
                }
            }
        }

        $dueDate = $mission->due_date?->toDateString();
        $isOverdue = $mission->due_date && $mission->due_date->lt($today) && ! $done;

        return [
            'id' => $mission->id,
            'type' => $type,
            'target_id' => $mission->missionable_id,
            'title' => $title,
            'meta' => $meta,
            'due_date' => $dueDate,
            'status' => $status,
            'progress_pct' => $progress,
            'score' => $score,
            'attempt_id' => $attemptId,
            'attempts_used' => $attemptsUsed,
            'attempts_allowed' => (int) ($mission->attempts_allowed ?? 1),
            'is_overdue' => (bool) $isOverdue,
            'done' => $done,
        ];
    }

    /** @return array{0:string,1:string,2:string} [type, title, meta] */
    private function describe($model): array
    {
        if ($model instanceof Document) {
            $label = $model->type === 'lecture' ? 'Bài giảng' : 'Tài liệu';
            $mins = $model->reading_minutes ? " · {$model->reading_minutes} phút đọc" : '';

            return ['document', $model->title, $label.$mins];
        }
        if ($model instanceof Deck) {
            $count = $model->cards()->count();

            return ['deck', $model->name, "{$count} thẻ từ"];
        }
        if ($model instanceof Test) {
            if ($model->skill === Skill::Writing) {
                $limit = $model->word_limit ? " · tối đa {$model->word_limit} từ" : '';

                return ['writing', $model->title, 'Bài viết'.$limit];
            }
            $skillLabel = match ($model->skill) {
                Skill::Listening => 'Đề nghe',
                Skill::Reading => 'Bài đọc',
                Skill::Mixed => 'Đề tổng hợp',
                default => 'Trắc nghiệm',
            };

            return ['test', $model->title, $skillLabel." · {$model->questionCount()} câu"];
        }

        return ['document', 'Nội dung', ''];
    }

    private function stats(Classroom $classroom, User $student, $sessionsOut, $sessionIds): array
    {
        $doneCount = $sessionsOut->sum('done');
        $totalCount = $sessionsOut->sum('total');

        // Chỉ điểm bài cô giao trong lớp này (mission_id != null) — điểm tự luyện ở Thư viện
        // không được kéo điểm TB của lớp lên/xuống.
        $myAvg = TestAttempt::where('classroom_id', $classroom->id)
            ->where('user_id', $student->id)
            ->whereNotNull('mission_id')
            ->whereIn('status', ['submitted', 'graded'])
            ->whereNotNull('total_score')
            ->avg('total_score');

        $attended = SessionAttendance::where('user_id', $student->id)
            ->whereIn('class_session_id', $sessionIds)
            ->where('status', 'present')
            ->count();

        return [
            'my_progress_pct' => $totalCount > 0 ? (int) round($doneCount / $totalCount * 100) : 0,
            'done_count' => $doneCount,
            'total_count' => $totalCount,
            'my_avg_score' => $myAvg !== null ? round((float) $myAvg, 1) : null,
            'class_avg_score' => $this->classStats->forClass($classroom)['avg_score'] ?? null,
            'attended_sessions' => $attended,
            'total_sessions' => $sessionIds->count(),
        ];
    }
}
