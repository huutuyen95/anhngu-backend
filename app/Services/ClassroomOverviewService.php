<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\Mission;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Support\Collection;

class ClassroomOverviewService
{
    public function __construct(
        private readonly ClassroomStatsService $stats,
        private readonly ClassSessionService $sessions,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forClass(Classroom $classroom): array
    {
        $stats = $this->stats->forClass($classroom);
        $students = $classroom->students()->get();
        $totalStudents = $students->count();

        $activeStudents = TestAttempt::where('classroom_id', $classroom->id)
            ->assigned()
            ->where('submitted_at', '>=', now()->subDays(30))
            ->distinct('user_id')
            ->count('user_id');

        $atRisk = $this->atRisk($classroom, $students);

        return [
            'stats' => [
                'progress_pct' => $stats['progress_pct'],
                'active_students' => $activeStudents,
                'total_students' => $totalStudents,
                'avg_score' => $stats['avg_score'],
                'pending_review' => $stats['pending_review_count'],
                'deltas' => null, // Chưa đủ dữ liệu lịch sử để tính delta.
            ],
            'sessions' => $this->sessions->listForClass($classroom)->values(),
            'at_risk' => $atRisk,
        ];
    }

    /**
     * Học viên cần chú ý: ≥2 bài quá hạn chưa làm · điểm TB < 6 · (không hoạt động ≥7 ngày).
     *
     * @param  Collection<int, User>  $students
     * @return array<int, array<string, mixed>>
     */
    private function atRisk(Classroom $classroom, $students): array
    {
        $today = now()->toDateString();
        $result = [];

        foreach ($students as $student) {
            $overdue = Mission::where('classroom_id', $classroom->id)
                ->where('user_id', $student->id)
                ->where('status', 'todo')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today)
                ->count();

            $avg = (float) (TestAttempt::where('classroom_id', $classroom->id)
                ->where('user_id', $student->id)
                ->assigned()
                ->scored()
                ->avg('total_score') ?? 0);

            $reason = null;
            $tag = null;
            if ($overdue >= 2) {
                $reason = "{$overdue} bài quá hạn chưa làm";
                $tag = 'at_risk';
            } elseif ($avg > 0 && $avg < 6) {
                $reason = 'Điểm trung bình '.round($avg, 1).' — dưới 6';
                $tag = 'low_score';
            }

            if ($reason) {
                $result[] = [
                    'user' => ['id' => $student->id, 'name' => $student->name],
                    'reason' => $reason,
                    'tag' => $tag,
                ];
            }

            if (count($result) >= 5) {
                break;
            }
        }

        return $result;
    }
}
