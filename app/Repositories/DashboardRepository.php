<?php

namespace App\Repositories;

use App\Models\Classroom;
use App\Models\Mission;
use App\Models\TestAttempt;
use Illuminate\Database\Eloquent\Collection;

class DashboardRepository
{
    public function activeClassCount(string $today): int
    {
        return Classroom::query()
            ->where(fn ($query) => $query->whereNull('starts_on')->orWhereDate('starts_on', '<=', $today))
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today))
            ->count();
    }

    public function pendingReviewCount(): int
    {
        return TestAttempt::where('status', 'submitted')->whereNull('total_score')->count();
    }

    public function openMissionCount(): int
    {
        return Mission::where('status', 'todo')->count();
    }

    public function averageScoreBetween(mixed $from, mixed $to = null): float
    {
        return (float) (TestAttempt::where('status', 'submitted')
            ->whereNotNull('total_score')
            ->when($to, fn ($query) => $query->whereBetween('submitted_at', [$from, $to]), fn ($query) => $query->where('submitted_at', '>=', $from))
            ->avg('total_score') ?? 0);
    }

    public function latestClasses(): Collection
    {
        return Classroom::query()->latest()->take(5)->get();
    }

    public function missionsDueOn(string $date): int
    {
        return Mission::where('status', 'todo')->whereDate('due_date', $date)->count();
    }

    public function latestActivities(): Collection
    {
        return TestAttempt::with(['user:id,name', 'test:id,title'])
            ->where('status', 'submitted')
            ->latest('submitted_at')
            ->take(5)
            ->get();
    }
}
