<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassroomResource;
use App\Models\Classroom;
use App\Models\Mission;
use App\Models\TestAttempt;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $today = now()->toDateString();

        $activeClasses = Classroom::query()
            ->where(fn ($s) => $s->whereNull('starts_on')->orWhereDate('starts_on', '<=', $today))
            ->where(fn ($s) => $s->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today))
            ->count();

        $pendingReview = TestAttempt::where('status', 'submitted')->whereNull('total_score')->count();
        $openMissions = Mission::where('status', 'todo')->count();

        $avgWeek = (float) (TestAttempt::where('status', 'submitted')
            ->whereNotNull('total_score')
            ->where('submitted_at', '>=', now()->subDays(7))
            ->avg('total_score') ?? 0);
        $avgPrev = (float) (TestAttempt::where('status', 'submitted')
            ->whereNotNull('total_score')
            ->whereBetween('submitted_at', [now()->subDays(14), now()->subDays(7)])
            ->avg('total_score') ?? 0);
        $delta = $avgPrev > 0 ? round($avgWeek - $avgPrev, 1) : null;

        $classes = Classroom::query()->latest()->take(5)->get();

        $todos = [];
        if ($pendingReview > 0) {
            $todos[] = [
                'type' => 'grading',
                'text' => "{$pendingReview} bài đang chờ cô chấm",
                'href' => '/teacher/grading',
            ];
        }
        $overdueToday = Mission::where('status', 'todo')->whereDate('due_date', $today)->count();
        if ($overdueToday > 0) {
            $todos[] = [
                'type' => 'due',
                'text' => "{$overdueToday} bài đến hạn hôm nay",
                'href' => '/teacher/results',
            ];
        }

        $activities = TestAttempt::with(['user:id,name', 'test:id,title'])
            ->where('status', 'submitted')
            ->latest('submitted_at')
            ->take(5)
            ->get()
            ->map(fn ($a) => [
                'type' => 'attempt',
                'text' => ($a->user?->name ?? 'Học sinh').' đã nộp '.($a->test?->title ?? 'một bài'),
                'time' => $a->submitted_at?->toIso8601String(),
            ]);

        return response()->json([
            'stats' => [
                'classes' => $activeClasses,
                'pending_review' => $pendingReview,
                'open_missions' => $openMissions,
                'avg_score_week' => round($avgWeek, 1),
                'delta' => $delta,
            ],
            'classes' => ClassroomResource::collection($classes),
            'todos' => $todos,
            'activities' => $activities,
        ]);
    }
}
