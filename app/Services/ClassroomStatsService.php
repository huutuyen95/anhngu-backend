<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\Mission;
use App\Models\TestAttempt;
use Illuminate\Support\Facades\Cache;

class ClassroomStatsService
{
    private const TTL = 900; // 15 phút

    /**
     * Số liệu tổng hợp của 1 lớp (cache 15 phút).
     *
     * @return array<string, mixed>
     */
    public function forClass(Classroom $classroom): array
    {
        return Cache::remember("class_stats_{$classroom->id}", self::TTL, function () use ($classroom) {
            return $this->compute($classroom);
        });
    }

    public function forget(Classroom $classroom): void
    {
        Cache::forget("class_stats_{$classroom->id}");
    }

    /**
     * @return array<string, mixed>
     */
    private function compute(Classroom $classroom): array
    {
        $studentsCount = $classroom->students()->count();

        // Chỉ tính bài ĐƯỢC GIAO (`assigned()` = attempt có mission_id), không tính tự luyện.
        $graded = TestAttempt::where('classroom_id', $classroom->id)
            ->assigned()
            ->scored();

        $avgScore = (float) ($graded->avg('total_score') ?? 0);

        $missions = Mission::where('classroom_id', $classroom->id);
        $totalMissions = (clone $missions)->count();
        $doneMissions = (clone $missions)->where('status', 'done')->count();
        $progressPct = $totalMissions > 0 ? (int) round($doneMissions / $totalMissions * 100) : 0;

        $openMissions = (clone $missions)->where('status', 'todo')->count();

        // "Chờ chấm": bài writing/speaking đã nộp, đang đợi cô chấm tay.
        $pendingReview = TestAttempt::where('classroom_id', $classroom->id)
            ->assigned()
            ->where('status', 'pending_review')
            ->count();

        $lastSession = $classroom->sessions()
            ->reorder()
            ->orderByDesc('order')
            ->first();

        return [
            'students_count' => $studentsCount,
            'sessions_count' => $classroom->sessions()->count(),
            'open_missions_count' => $openMissions,
            'pending_review_count' => $pendingReview,
            'progress_pct' => $progressPct,
            'avg_score' => round($avgScore, 1),
            'last_session' => $lastSession ? [
                'title' => $lastSession->title,
                'held_on' => $lastSession->held_on?->toDateString(),
            ] : null,
        ];
    }
}
