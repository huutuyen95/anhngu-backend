<?php

namespace App\Repositories;

use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Mission;
use App\Models\SessionAttendance;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportRepository
{
    public function gradedSummary(Classroom $classroom, Carbon $from): array
    {
        $query = TestAttempt::where('classroom_id', $classroom->id)->assigned()->scored()->where('submitted_at', '>=', $from);

        return [
            'attempts' => (clone $query)->count(),
            'active_students' => (clone $query)->distinct('user_id')->count('user_id'),
        ];
    }

    public function studyAttempts(Classroom $classroom, Carbon $from): Collection
    {
        return TestAttempt::where('classroom_id', $classroom->id)
            ->assigned()->whereNotNull('started_at')->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', $from)->get(['started_at', 'submitted_at']);
    }

    public function completedMissions(Classroom $classroom, Carbon $from): int
    {
        return Mission::where('classroom_id', $classroom->id)->where('status', 'done')->where('updated_at', '>=', $from)->count();
    }

    public function pendingReview(Classroom $classroom): int
    {
        return TestAttempt::where('classroom_id', $classroom->id)->assigned()->where('status', 'pending_review')->count();
    }

    public function averageBetween(Classroom $classroom, mixed $start, mixed $end): float
    {
        return (float) (TestAttempt::where('classroom_id', $classroom->id)->assigned()->scored()
            ->whereBetween('submitted_at', [$start, $end])->avg('total_score') ?? 0);
    }

    public function scores(Classroom $classroom, Carbon $from): Collection
    {
        return TestAttempt::where('classroom_id', $classroom->id)->assigned()->scored()
            ->where('submitted_at', '>=', $from)->pluck('total_score');
    }

    public function sessions(Classroom $classroom): Collection
    {
        return $classroom->sessions()->get();
    }

    public function sessionMissionCounts(ClassSession $session): array
    {
        $query = Mission::where('class_session_id', $session->id);

        return ['assigned' => (clone $query)->count(), 'done' => (clone $query)->where('status', 'done')->count()];
    }

    public function students(Classroom $classroom): Collection
    {
        return $classroom->students()->orderBy('name')->get();
    }

    public function studentMetrics(Classroom $classroom, User $student): array
    {
        $missions = Mission::where('classroom_id', $classroom->id)->where('user_id', $student->id);
        $attempts = TestAttempt::where('classroom_id', $classroom->id)->where('user_id', $student->id)->assigned()->scored();

        return [
            'total_missions' => (clone $missions)->count(),
            'done_missions' => (clone $missions)->where('status', 'done')->count(),
            'attempts' => (clone $attempts)->count(),
            'low_score_count' => (clone $attempts)->where('total_score', '<', 6)->count(),
            'attended' => SessionAttendance::where('user_id', $student->id)
                ->whereIn('status', ['on_time', 'late'])
                ->whereHas('session', fn ($query) => $query->where('classroom_id', $classroom->id))->count(),
            'last_week_score' => (float) (TestAttempt::where('classroom_id', $classroom->id)->where('user_id', $student->id)
                ->assigned()->scored()->whereBetween('submitted_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])
                ->avg('total_score') ?? 0),
        ];
    }
}
