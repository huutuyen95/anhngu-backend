<?php

namespace App\Services;

use App\Exports\ClassReportExport;
use App\Models\Classroom;
use App\Repositories\ReportRepository;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportService
{
    public function __construct(private readonly ReportRepository $reports) {}

    public function classReport(Classroom $classroom, string $period): array
    {
        $days = match ($period) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };
        $from = now()->subDays($days);
        $summary = $this->reports->gradedSummary($classroom, $from);

        return [
            'stats' => [
                'active_students' => $summary['active_students'],
                'completed' => $this->reports->completedMissions($classroom, $from),
                'attempts' => $summary['attempts'],
                'study_seconds' => $this->studySeconds($classroom, $from),
                'delta' => null,
            ],
            'weekly_avg' => $this->weeklyAverage($classroom, $days),
            'score_buckets' => $this->scoreBuckets($classroom, $from),
            'by_session' => $this->bySession($classroom),
            'by_student' => $this->byStudent($classroom),
            'pending_count' => $this->reports->pendingReview($classroom),
        ];
    }

    public function export(Classroom $classroom, string $period): BinaryFileResponse
    {
        $report = $this->classReport($classroom, $period);

        return Excel::download(new ClassReportExport($report['by_student']), "bao-cao-lop-{$classroom->id}.xlsx");
    }

    private function studySeconds(Classroom $classroom, Carbon $from): int
    {
        return (int) $this->reports->studyAttempts($classroom, $from)
            ->sum(fn ($attempt) => max(0, $attempt->submitted_at->diffInSeconds($attempt->started_at)));
    }

    private function weeklyAverage(Classroom $classroom, int $days): array
    {
        $output = [];
        for ($index = max(1, (int) ceil($days / 7)) - 1; $index >= 0; $index--) {
            $start = now()->subWeeks($index)->startOfWeek();
            $end = now()->subWeeks($index)->endOfWeek();
            $output[] = ['week' => $start->format('d/m'), 'score' => round($this->reports->averageBetween($classroom, $start, $end), 1)];
        }

        return $output;
    }

    private function scoreBuckets(Classroom $classroom, Carbon $from): array
    {
        $scores = $this->reports->scores($classroom, $from);

        return array_map(fn ($range) => [
            'range' => $range[0].'–'.($range[1] > 10 ? 10 : $range[1]),
            'count' => $scores->filter(fn ($score) => $score >= $range[0] && $score < $range[1])->count(),
        ], [[0, 2], [2, 4], [4, 6], [6, 8], [8, 10.01]]);
    }

    private function bySession(Classroom $classroom): array
    {
        return $this->reports->sessions($classroom)->map(function ($session): array {
            $counts = $this->reports->sessionMissionCounts($session);

            return [
                'session' => $session->title,
                'assigned' => $counts['assigned'],
                'done' => $counts['done'],
                'completion_pct' => $counts['assigned'] > 0 ? (int) round($counts['done'] / $counts['assigned'] * 100) : 0,
            ];
        })->all();
    }

    private function byStudent(Classroom $classroom): array
    {
        return $this->reports->students($classroom)->map(function ($student) use ($classroom): array {
            $metrics = $this->reports->studentMetrics($classroom, $student);

            return [
                'user' => ['id' => $student->id, 'name' => $student->name],
                'completion_pct' => $metrics['total_missions'] > 0 ? (int) round($metrics['done_missions'] / $metrics['total_missions'] * 100) : 0,
                'attempts' => $metrics['attempts'],
                'study_seconds' => 0,
                'low_score_count' => $metrics['low_score_count'],
                'attended' => $metrics['attended'],
                'last_week_score' => round($metrics['last_week_score'], 1),
            ];
        })->all();
    }
}
