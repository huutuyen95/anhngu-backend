<?php

namespace App\Services;

use App\Repositories\DashboardRepository;

class DashboardService
{
    public function __construct(private readonly DashboardRepository $repository) {}

    public function data(): array
    {
        $today = now()->toDateString();
        $pendingReview = $this->repository->pendingReviewCount();
        $averageWeek = $this->repository->averageScoreBetween(now()->subDays(7));
        $averagePrevious = $this->repository->averageScoreBetween(now()->subDays(14), now()->subDays(7));
        $todos = [];
        if ($pendingReview > 0) {
            $todos[] = ['type' => 'grading', 'text' => "{$pendingReview} bài đang chờ cô chấm", 'href' => '/teacher/grading'];
        }
        $dueToday = $this->repository->missionsDueOn($today);
        if ($dueToday > 0) {
            $todos[] = ['type' => 'due', 'text' => "{$dueToday} bài đến hạn hôm nay", 'href' => '/teacher/results'];
        }

        return [
            'stats' => [
                'classes' => $this->repository->activeClassCount($today),
                'pending_review' => $pendingReview,
                'open_missions' => $this->repository->openMissionCount(),
                'avg_score_week' => round($averageWeek, 1),
                'delta' => $averagePrevious > 0 ? round($averageWeek - $averagePrevious, 1) : null,
            ],
            'classes' => $this->repository->latestClasses(),
            'todos' => $todos,
            'activities' => $this->repository->latestActivities()->map(fn ($attempt) => [
                'type' => 'attempt',
                'text' => ($attempt->user?->name ?? 'Học sinh').' đã nộp '.($attempt->test?->title ?? 'một bài'),
                'time' => $attempt->submitted_at?->toIso8601String(),
            ]),
        ];
    }
}
