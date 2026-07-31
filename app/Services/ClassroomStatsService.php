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

        // Chỉ tính bài ĐƯỢC GIAO (attempt gắn classroom_id), không tính tự luyện (classroom_id null).
        $graded = TestAttempt::where('classroom_id', $classroom->id)
            ->where('status', 'submitted')
            ->whereNotNull('total_score');

        $avgScore = (float) ($graded->avg('total_score') ?? 0);

        $missions = Mission::where('classroom_id', $classroom->id);
        $totalMissions = (clone $missions)->count();
        $doneMissions = (clone $missions)->where('status', 'done')->count();
        $progressPct = $totalMissions > 0 ? (int) round($doneMissions / $totalMissions * 100) : 0;

        $openMissions = (clone $missions)->where('status', 'todo')->count();

        // "Chờ chấm": bài đã nộp nhưng chưa có điểm (writing chờ cô chấm).
        $pendingReview = TestAttempt::where('classroom_id', $classroom->id)
            ->where('status', 'submitted')
            ->whereNull('total_score')
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
