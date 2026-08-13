<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\Mission;
use App\Models\SessionAttendance;
use App\Models\TestAttempt;
use Illuminate\Support\Carbon;

class ReportService
{
    /**
     * Báo cáo của 1 lớp theo kỳ. Chỉ tính bài ĐƯỢC GIAO (scope `assigned()` = attempt có
     * mission_id); lượt tự luyện ở Thư viện không bao giờ lọt vào đây.
     * Bài chờ chấm (total_score null) KHÔNG tính vào điểm TB — trả kèm pending_count.
     *
     * @return array<string, mixed>
     */
    public function classReport(Classroom $classroom, string $period): array
    {
        $days = match ($period) {
            '7d' => 7, '90d' => 90, default => 30,
        };
        $from = now()->subDays($days);

        $graded = TestAttempt::where('classroom_id', $classroom->id)
            ->assigned()
            ->scored()
            ->where('submitted_at', '>=', $from);

        $attemptsCount = (clone $graded)->count();
        $activeStudents = (clone $graded)->distinct('user_id')->count('user_id');
        $studySeconds = $this->studySeconds($classroom->id, $from);

        $completed = Mission::where('classroom_id', $classroom->id)
            ->where('status', 'done')
            ->where('updated_at', '>=', $from)
            ->count();

        $pending = TestAttempt::where('classroom_id', $classroom->id)
            ->assigned()->where('status', 'pending_review')->count();

        return [
            'stats' => [
                'active_students' => $activeStudents,
                'completed' => $completed,
                'attempts' => $attemptsCount,
                'study_seconds' => $studySeconds,
                'delta' => null,
            ],
            'weekly_avg' => $this->weeklyAvg($classroom->id, $days),
            'score_buckets' => $this->scoreBuckets($classroom->id, $from),
            'by_session' => $this->bySession($classroom),
            'by_student' => $this->byStudent($classroom, $from),
            'pending_count' => $pending,
        ];
    }

    private function studySeconds(int $classId, Carbon $from): int
    {
        return (int) TestAttempt::where('classroom_id', $classId)
            ->assigned()
            ->whereNotNull('started_at')->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', $from)
            ->get(['started_at', 'submitted_at'])
            ->sum(fn ($a) => max(0, $a->submitted_at->diffInSeconds($a->started_at)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function weeklyAvg(int $classId, int $days): array
    {
        $weeks = max(1, (int) ceil($days / 7));
        $out = [];
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $start = now()->subWeeks($i)->startOfWeek();
            $end = now()->subWeeks($i)->endOfWeek();
            $avg = (float) (TestAttempt::where('classroom_id', $classId)
                ->assigned()->scored()
                ->whereBetween('submitted_at', [$start, $end])
                ->avg('total_score') ?? 0);
            $out[] = ['week' => $start->format('d/m'), 'score' => round($avg, 1)];
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scoreBuckets(int $classId, Carbon $from): array
    {
        $scores = TestAttempt::where('classroom_id', $classId)
            ->assigned()->scored()
            ->where('submitted_at', '>=', $from)
            ->pluck('total_score');

        $ranges = [[0, 2], [2, 4], [4, 6], [6, 8], [8, 10.01]];

        return array_map(fn ($r) => [
            'range' => $r[0].'–'.($r[1] > 10 ? 10 : $r[1]),
            'count' => $scores->filter(fn ($s) => $s >= $r[0] && $s < $r[1])->count(),
        ], $ranges);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bySession(Classroom $classroom): array
    {
        return $classroom->sessions()->get()->map(function ($s) {
            $missions = Mission::where('class_session_id', $s->id);
            $assigned = (clone $missions)->count();
            $done = (clone $missions)->where('status', 'done')->count();

            return [
                'session' => $s->title,
                'assigned' => $assigned,
                'done' => $done,
                'completion_pct' => $assigned > 0 ? (int) round($done / $assigned * 100) : 0,
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function byStudent(Classroom $classroom, Carbon $from): array
    {
        return $classroom->students()->orderBy('name')->get()->map(function ($student) use ($classroom) {
            $missions = Mission::where('classroom_id', $classroom->id)->where('user_id', $student->id);
            $total = (clone $missions)->count();
            $done = (clone $missions)->where('status', 'done')->count();

            $attempts = TestAttempt::where('classroom_id', $classroom->id)->where('user_id', $student->id)
                ->assigned()->scored();

            $attended = SessionAttendance::where('user_id', $student->id)
                ->whereIn('status', ['on_time', 'late'])
                ->whereHas('session', fn ($q) => $q->where('classroom_id', $classroom->id))
                ->count();

            $lastWeek = (float) (TestAttempt::where('classroom_id', $classroom->id)->where('user_id', $student->id)
                ->assigned()->scored()
                ->whereBetween('submitted_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])
                ->avg('total_score') ?? 0);

            return [
                'user' => ['id' => $student->id, 'name' => $student->name],
                'completion_pct' => $total > 0 ? (int) round($done / $total * 100) : 0,
                'attempts' => (clone $attempts)->count(),
                'study_seconds' => 0,
                'low_score_count' => (clone $attempts)->where('total_score', '<', 6)->count(),
                'attended' => $attended,
                'last_week_score' => round($lastWeek, 1),
            ];
        })->all();
    }
}
