<?php

namespace App\Repositories;

use App\Models\Classroom;
use App\Models\Mission;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ClassroomAnalyticsRepository
{
    public function students(Classroom $classroom): Collection
    {
        return $classroom->students()->get();
    }

    public function activeStudents(Classroom $classroom): int
    {
        return TestAttempt::where('classroom_id', $classroom->id)
            ->assigned()
            ->where('submitted_at', '>=', now()->subDays(30))
            ->distinct('user_id')
            ->count('user_id');
    }

    public function overdueMissions(Classroom $classroom, User $student, string $today): int
    {
        return Mission::where('classroom_id', $classroom->id)
            ->where('user_id', $student->id)
            ->where('status', 'todo')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->count();
    }

    public function studentAverage(Classroom $classroom, User $student): float
    {
        return (float) (TestAttempt::where('classroom_id', $classroom->id)
            ->where('user_id', $student->id)
            ->assigned()
            ->scored()
            ->avg('total_score') ?? 0);
    }

    public function stats(Classroom $classroom): array
    {
        $missions = Mission::where('classroom_id', $classroom->id);
        $totalMissions = (clone $missions)->count();
        $doneMissions = (clone $missions)->where('status', 'done')->count();
        $lastSession = $classroom->sessions()->reorder()->orderByDesc('order')->first();

        return [
            'students_count' => $classroom->students()->count(),
            'sessions_count' => $classroom->sessions()->count(),
            'open_missions_count' => (clone $missions)->where('status', 'todo')->count(),
            'pending_review_count' => TestAttempt::where('classroom_id', $classroom->id)->assigned()->where('status', 'pending_review')->count(),
            'progress_pct' => $totalMissions > 0 ? (int) round($doneMissions / $totalMissions * 100) : 0,
            'avg_score' => round((float) (TestAttempt::where('classroom_id', $classroom->id)->assigned()->scored()->avg('total_score') ?? 0), 1),
            'last_session' => $lastSession ? ['title' => $lastSession->title, 'held_on' => $lastSession->held_on?->toDateString()] : null,
        ];
    }
}
