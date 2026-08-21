<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\TestAttempt;
use App\Models\User;
use App\Repositories\StudentReportRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tổng hợp số liệu Báo cáo của học sinh (scope=overview: mọi lớp đang join; scope=class: 1 lớp).
 * Chỉ tính bài CÔ GIAO (test_attempts.mission_id != null). Bài chờ chấm (pending_review) KHÔNG
 * tính vào điểm trung bình. Mọi truy vấn DB đi qua StudentReportRepository.
 */
class StudentReportService
{
    private const PERIOD_DAYS = ['7d' => 7, '30d' => 30, '90d' => 90];

    public function __construct(private readonly StudentReportRepository $repo) {}

    /** @return array<string,mixed> */
    public function report(User $student, string $scope, ?int $classroomId, string $period): array
    {
        if ($scope === 'class' && $classroomId) {
            abort_unless($this->repo->isMember($student, $classroomId), 403, 'Em không ở trong lớp này.');
        }

        $days = self::PERIOD_DAYS[$period] ?? 30;
        $from = now()->subDays($days)->startOfDay();

        $classIds = $scope === 'class' && $classroomId ? collect([$classroomId]) : $this->repo->classIds($student);

        // 4 mốc tuần (index 0 = cũ nhất, 3 = tuần hiện tại) cho mini-plot + biểu đồ kỹ năng.
        $weeks = collect(range(0, 3))->map(fn ($i) => [
            'start' => now()->startOfWeek()->subWeeks(3 - $i),
            'end' => now()->startOfWeek()->subWeeks(3 - $i)->addWeek(),
        ]);
        $window = min($from, $weeks->first()['start']);

        $attempts = $this->repo->attempts($student->id, $classIds, $window);
        $missions = $this->repo->missions($student->id, $classIds);
        $deckLogs = $this->repo->deckLogs($student->id, $scope === 'class' ? $classroomId : null, $window);

        return [
            'scope' => $scope,
            'period' => $period,
            'stats' => $this->stats($attempts, $missions, $deckLogs, $from, $weeks),
            'skills' => $this->skills($attempts, $weeks),
            'class_progress' => $this->classProgress($student, $classIds),
            'activity_mix' => $this->activityMix($missions, $from),
            'test_history' => $this->testHistory($attempts),
            'activity_7d' => $this->activity7d($student, $scope === 'class' ? $classroomId : null),
        ];
    }

    /** @param  Collection<int,TestAttempt>  $attempts */
    private function stats(Collection $attempts, Collection $missions, Collection $deckLogs, Carbon $from, Collection $weeks): array
    {
        $scored = fn (Collection $a) => $a->whereIn('status', ['submitted', 'graded'])->filter(fn ($x) => $x->total_score !== null);
        $pct = fn (TestAttempt $x) => $x->test && $x->test->total_score > 0
            ? min(100, round($x->total_score / $x->test->total_score * 100)) : 0;

        $inPeriod = $attempts->filter(fn ($x) => $x->started_at >= $from);
        $periodScored = $scored($inPeriod);
        $doneMissions = $missions->where('status', 'done');

        $weekly = ['score' => [], 'completed' => [], 'attempts' => [], 'minutes' => []];
        foreach ($weeks as $w) {
            $wa = $attempts->filter(fn ($x) => $x->started_at >= $w['start'] && $x->started_at < $w['end']);
            $ws = $scored($wa);
            $wLogs = $deckLogs->filter(fn ($l) => $l->created_at >= $w['start'] && $l->created_at < $w['end']);
            $weekly['score'][] = $ws->isNotEmpty() ? (int) round($ws->avg($pct)) : 0;
            $weekly['attempts'][] = $wa->count();
            $weekly['completed'][] = $doneMissions->filter(fn ($m) => $m->completed_at && $m->completed_at >= $w['start'] && $m->completed_at < $w['end'])->count();
            $weekly['minutes'][] = (int) round($this->studySeconds($wa, $wLogs) / 60);
        }

        return [
            'avg_score' => $periodScored->isNotEmpty() ? (int) round($periodScored->avg($pct)) : 0,
            'completed' => $doneMissions->filter(fn ($m) => $m->completed_at && $m->completed_at >= $from)->count(),
            'attempts' => $inPeriod->count(),
            'study_seconds' => $this->studySeconds($inPeriod, $deckLogs->where('created_at', '>=', $from)),
            'weekly' => $weekly,
            'notes' => [
                'avg_score' => $this->note($weekly['score']),
                'completed' => $this->note($weekly['completed']),
                'attempts' => $this->note($weekly['attempts']),
                'study' => $this->note($weekly['minutes']),
            ],
        ];
    }

    /** Tổng thời gian học (giây): thời lượng lượt làm + thời lượng học flashcard. */
    private function studySeconds(Collection $attempts, Collection $deckLogs): int
    {
        $attemptSec = $attempts->filter(fn ($x) => $x->submitted_at && $x->started_at)
            ->sum(fn ($x) => max(0, $x->started_at->diffInSeconds($x->submitted_at)));

        return (int) ($attemptSec + $deckLogs->sum('duration_seconds'));
    }

    /** @return array{dir:string,text:string} */
    private function note(array $series): array
    {
        $last = end($series) ?: 0;
        $prev = $series[count($series) - 2] ?? 0;
        if ($last > $prev) {
            return ['dir' => 'up', 'text' => 'Đang tiến bộ, giữ nhịp nhé!'];
        }
        if ($last < $prev) {
            return ['dir' => 'down', 'text' => 'Giảm nhẹ — cố thêm chút nữa nào.'];
        }

        return ['dir' => 'flat', 'text' => 'Giữ phong độ ổn định.'];
    }

    /** Điểm % theo 4 tuần cho 4 kỹ năng (mixed gộp vào Đọc). */
    private function skills(Collection $attempts, Collection $weeks): array
    {
        $pct = fn (TestAttempt $x) => $x->test && $x->test->total_score > 0
            ? min(100, round($x->total_score / $x->test->total_score * 100)) : 0;
        $skillOf = fn (TestAttempt $x) => match ($x->test?->skill?->value) {
            'listening' => 'listening', 'writing' => 'writing', 'speaking' => 'speaking',
            default => 'reading',
        };

        $out = ['listening' => [], 'reading' => [], 'writing' => [], 'speaking' => []];
        foreach ($weeks as $w) {
            $wa = $attempts->filter(fn ($x) => $x->started_at >= $w['start'] && $x->started_at < $w['end']
                && in_array($x->status, ['submitted', 'graded'], true) && $x->total_score !== null);
            foreach ($out as $skill => &$series) {
                $sa = $wa->filter(fn ($x) => $skillOf($x) === $skill);
                $series[] = $sa->isNotEmpty() ? (int) round($sa->avg($pct)) : 0;
            }
            unset($series);
        }

        return $out;
    }

    private function classProgress(User $student, Collection $classIds): array
    {
        return $this->repo->classes($student, $classIds)->map(function (array $c) use ($student) {
            $counts = $this->repo->classMissionCounts($student->id, $c['id']);

            return [
                'classroom_id' => $c['id'],
                'name' => $c['name'],
                'done' => $counts['done'],
                'total' => $counts['total'],
                'pct' => $counts['total'] > 0 ? (int) round($counts['done'] / $counts['total'] * 100) : 0,
            ];
        })->values()->all();
    }

    /** Donut hoạt động — đếm nhiệm vụ đã xong trong kỳ theo loại. */
    private function activityMix(Collection $missions, Carbon $from): array
    {
        $done = $missions->where('status', 'done')->filter(fn ($m) => $m->completed_at && $m->completed_at >= $from);
        $counts = ['exercise' => 0, 'test' => 0, 'vocab' => 0, 'speaking' => 0];
        foreach ($done as $m) {
            // missionable_type là alias morphMap ('test'|'deck'|'document') chứ không phải class name.
            $type = strtolower(class_basename($m->missionable_type));
            if ($type === 'deck') {
                $counts['vocab']++;
            } elseif ($type === 'document') {
                $counts['exercise']++;
            } elseif ($type === 'test') {
                $counts[$m->missionable?->skill?->value === 'speaking' ? 'speaking' : 'test']++;
            }
        }

        return collect($counts)->map(fn ($count, $type) => ['type' => $type, 'count' => $count])->values()->all();
    }

    /** @param  Collection<int,TestAttempt>  $attempts */
    private function testHistory(Collection $attempts): array
    {
        return $attempts
            ->whereIn('status', ['submitted', 'graded'])
            ->filter(fn ($x) => $x->submitted_at)
            ->sortByDesc('submitted_at')
            ->take(10)
            ->map(fn (TestAttempt $x) => [
                'attempt_id' => $x->id,
                'test_id' => $x->test_id,
                'test_name' => $x->test?->title ?? 'Đề thi',
                'score' => $x->total_score !== null ? round((float) $x->total_score, 1) : null,
                'pending' => $x->status === 'pending_review',
                'taken_at' => $x->submitted_at?->toIso8601String(),
            ])->values()->all();
    }

    private function activity7d(User $student, ?int $classroomId): array
    {
        $map = [
            'deck_study' => 'vocab', 'deck' => 'vocab',
            'test' => 'test', 'attempt' => 'test', 'test_attempt' => 'test',
            'document' => 'exercise',
        ];

        return $this->repo->activity7d($student->id, $classroomId)
            ->map(fn (ActivityLog $l) => [
                'id' => $l->id,
                'name' => $l->subject ?? 'Hoạt động',
                'category' => $map[$l->type] ?? 'exercise',
                'status' => $l->score !== null ? 'done' : 'viewed',
                'at' => $l->created_at?->toIso8601String(),
                'target_type' => ($l->meta['deck_id'] ?? null) ? 'deck' : (($l->meta['test_id'] ?? null) ? 'test' : null),
                'target_id' => $l->meta['deck_id'] ?? $l->meta['test_id'] ?? null,
            ])->values()->all();
    }
}
