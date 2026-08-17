<?php

namespace App\Services;

use App\Enums\Skill;
use App\Models\Classroom;
use App\Models\Deck;
use App\Models\Document;
use App\Models\Mission;
use App\Models\Test;
use App\Models\User;
use App\Repositories\StudentRoadmapRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * Dựng "lộ trình" (roadmap) trang Lớp của em cho một học sinh:
 * danh sách buổi (chỉ buổi cô cho hiện) + nội dung từng buổi kèm trạng thái riêng của em.
 */
class StudentRoadmapService
{
    public function __construct(
        private readonly ClassroomStatsService $classStats,
        private readonly StudentRoadmapRepository $roadmaps,
    ) {}

    /** Danh sách lớp học sinh đang tham gia (dữ liệu card màn chọn lớp). */
    public function myClassrooms(User $student): array
    {
        $today = now()->startOfDay();
        $dueSoonLimit = now()->addDays(3)->endOfDay();
        $statusRank = ['active' => 0, 'upcoming' => 1, 'ended' => 2];

        return $this->roadmaps->classrooms($student)
            ->map(function (Classroom $c) use ($student, $today, $dueSoonLimit) {
                // Nhiệm vụ được giao cho em trong lớp (bỏ nháp). status='done' do các luồng
                // nộp bài / học deck / xem tài liệu tự set → đếm theo đó là chuẩn.
                $metrics = $this->roadmaps->classroomMetrics($c, $student, $today, $dueSoonLimit);
                $total = $metrics['total'];
                $done = $metrics['done'];

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'code' => $this->classCode($c->name),
                    'cover_url' => $c->cover_url,
                    'teacher_name' => $c->teacher?->name,
                    'students_count' => $c->students_count,
                    'schedule_text' => null, // chưa có cột lịch học trong classrooms
                    'starts_on' => $c->starts_on?->toDateString(),
                    'ends_on' => $c->ends_on?->toDateString(),
                    'status' => $c->status(),
                    'progress_pct' => $total > 0 ? (int) round($done / $total * 100) : 0,
                    'done_count' => $done,
                    'total_count' => $total,
                    'todo_count' => $total - $done,
                    'due_soon_count' => $metrics['due_soon'],
                    'avg_score' => $metrics['average'] !== null ? round((float) $metrics['average'], 1) : null,
                    'last_activity_at' => $metrics['last_activity'],
                    // giữ tương thích màn cũ
                    'my_progress_pct' => $total > 0 ? (int) round($done / $total * 100) : 0,
                ];
            })
            ->sort(function ($a, $b) use ($statusRank) {
                // active trước (due_soon nhiều lên đầu, rồi ends_on gần nhất), rồi upcoming, cuối ended.
                $ra = $statusRank[$a['status']] ?? 3;
                $rb = $statusRank[$b['status']] ?? 3;
                if ($ra !== $rb) {
                    return $ra <=> $rb;
                }
                if ($a['status'] === 'active') {
                    if ($a['due_soon_count'] !== $b['due_soon_count']) {
                        return $b['due_soon_count'] <=> $a['due_soon_count'];
                    }

                    return ($a['ends_on'] ?? '9999') <=> ($b['ends_on'] ?? '9999');
                }

                return 0;
            })
            ->values()
            ->all();
    }

    /** Suy mã lớp ngắn từ tên (vd "Lớp 6A1 buổi tối" → "6A1"); fallback 3 ký tự đầu. */
    private function classCode(string $name): string
    {
        if (preg_match('/\b\d[\p{L}\d]*\b/u', $name, $m)) {
            return mb_strtoupper($m[0]);
        }

        return mb_strtoupper(mb_substr(trim($name), 0, 3));
    }

    /** Lộ trình đầy đủ của một lớp cho học sinh. */
    public function roadmap(Classroom $classroom, User $student): array
    {
        $classroom = $this->roadmaps->loadClassroom($classroom);
        $sessions = $this->roadmaps->visibleSessions($classroom);

        $sessionIds = $sessions->pluck('id');
        $today = now()->startOfDay();

        // Missions của em trong lớp (bỏ nháp + lịch chưa tới giờ), kèm nội dung.
        $missions = $this->roadmaps->missions($classroom, $student, $sessionIds);

        // Nạp trước dữ liệu tiến độ để tránh N+1.
        $docIds = $missions->filter(fn ($m) => $m->missionable instanceof Document)->pluck('missionable_id');
        $deckModels = $missions->filter(fn ($m) => $m->missionable instanceof Deck)->pluck('missionable');

        // Gom theo MISSION chứ không theo test: lượt em tự luyện cùng đề đó ở Thư viện
        // (mission_id = null) không được tính là đã làm bài cô giao.
        $attempts = $this->roadmaps->attempts($student, $missions->pluck('id'));

        $views = $this->roadmaps->documentViews($student, $docIds);

        $deckProgress = $this->deckProgress($student, $deckModels, $classroom);
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
                // Buổi bị khoá (chưa tới lịch) không lộ nội dung.
                'items' => $locked ? [] : $items->map(fn ($i) => Arr::except($i, ['done']))->values(),
            ];
        })->values();

        return [
            'classroom' => [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'code' => $this->classCode($classroom->name),
                'description' => $classroom->description,
                'teacher_name' => $classroom->teacher?->name,
                'students_count' => $this->roadmaps->studentCount($classroom),
                'starts_on' => $classroom->starts_on?->toDateString(),
                'ends_on' => $classroom->ends_on?->toDateString(),
                'status' => $classroom->status(),
            ],
            'stats' => $this->stats($classroom, $student, $sessionsOut, $sessionIds),
            'sessions' => $sessionsOut->all(),
        ];
    }

    /**
     * Tiến độ deck theo SCOPE lớp — chỉ đếm card_progress có classroom_id = lớp này,
     * KHÔNG lẫn tiến độ tự luyện Thư viện (classroom_id null).
     *
     * @return array<int, array{known:int,total:int}>
     */
    private function deckProgress(User $student, $decks, Classroom $classroom): array
    {
        return $this->roadmaps->deckProgress($student, collect($decks), $classroom);
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
            $count = $this->roadmaps->deckCardCount($model);

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

            return ['test', $model->title, $skillLabel.' · '.$this->roadmaps->testQuestionCount($model).' câu'];
        }

        return ['document', 'Nội dung', ''];
    }

    private function stats(Classroom $classroom, User $student, $sessionsOut, $sessionIds): array
    {
        $doneCount = $sessionsOut->sum('done');
        $totalCount = $sessionsOut->sum('total');

        // Chỉ điểm bài cô giao trong lớp này (mission_id != null) — điểm tự luyện ở Thư viện
        // không được kéo điểm TB của lớp lên/xuống.
        $studentStats = $this->roadmaps->studentStats($classroom, $student, collect($sessionIds));

        return [
            'my_progress_pct' => $totalCount > 0 ? (int) round($doneCount / $totalCount * 100) : 0,
            'done_count' => $doneCount,
            'total_count' => $totalCount,
            'my_avg_score' => $studentStats['average'] !== null ? round((float) $studentStats['average'], 1) : null,
            'class_avg_score' => $this->classStats->forClass($classroom)['avg_score'] ?? null,
            'attended_sessions' => $studentStats['attended'],
            'total_sessions' => $sessionIds->count(),
        ];
    }
}
