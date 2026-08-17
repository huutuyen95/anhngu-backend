<?php

namespace App\Repositories;

use App\Models\CardProgress;
use App\Models\Classroom;
use App\Models\Deck;
use App\Models\DocumentView;
use App\Models\Mission;
use App\Models\SessionAttendance;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Support\Collection;

class StudentRoadmapRepository
{
    public function loadClassroom(Classroom $classroom): Classroom
    {
        return $classroom->load('teacher:id,name');
    }

    public function classrooms(User $student): Collection
    {
        return $student->classes()->with('teacher:id,name')->withCount('students')->get();
    }

    public function classroomMetrics(Classroom $classroom, User $student, mixed $today, mixed $dueSoonLimit): array
    {
        $missions = Mission::where('classroom_id', $classroom->id)->where('user_id', $student->id)->where('status', '!=', 'draft');

        return [
            'total' => (clone $missions)->count(),
            'done' => (clone $missions)->where('status', 'done')->count(),
            'due_soon' => (clone $missions)->where('status', '!=', 'done')->whereNotNull('due_date')->whereBetween('due_date', [$today, $dueSoonLimit])->count(),
            'average' => TestAttempt::where('classroom_id', $classroom->id)->where('user_id', $student->id)
                ->whereNotNull('mission_id')->whereIn('status', ['submitted', 'graded'])->whereNotNull('total_score')->avg('total_score'),
            'last_activity' => (clone $missions)->max('completed_at') ?? (clone $missions)->max('updated_at'),
        ];
    }

    public function visibleSessions(Classroom $classroom): Collection
    {
        return $classroom->sessions()->where('is_visible', true)->orderBy('order')->get();
    }

    public function missions(Classroom $classroom, User $student, Collection $sessionIds): Collection
    {
        return Mission::where('classroom_id', $classroom->id)
            ->where('user_id', $student->id)->whereIn('class_session_id', $sessionIds)
            ->where('status', '!=', 'draft')
            ->where(fn ($query) => $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))
            ->with('missionable')->get();
    }

    public function attempts(User $student, Collection $missionIds): Collection
    {
        return TestAttempt::where('user_id', $student->id)->whereIn('mission_id', $missionIds)->get()->groupBy('mission_id');
    }

    public function documentViews(User $student, Collection $documentIds): Collection
    {
        return DocumentView::where('user_id', $student->id)->whereIn('document_id', $documentIds)->get()->keyBy('document_id');
    }

    public function deckProgress(User $student, Collection $decks, Classroom $classroom): array
    {
        $output = [];
        foreach ($decks as $deck) {
            $cardIds = $deck->cards()->pluck('id');
            $output[$deck->id] = [
                'known' => $cardIds->isEmpty() ? 0 : CardProgress::where('user_id', $student->id)
                    ->whereIn('card_id', $cardIds)->where('classroom_id', $classroom->id)->where('status', 'known')->count(),
                'total' => $cardIds->count(),
            ];
        }

        return $output;
    }

    public function studentCount(Classroom $classroom): int
    {
        return $classroom->students()->count();
    }

    public function deckCardCount(Deck $deck): int
    {
        return $deck->cards()->count();
    }

    public function testQuestionCount(Test $test): int
    {
        return $test->questionCount();
    }

    public function studentStats(Classroom $classroom, User $student, Collection $sessionIds): array
    {
        return [
            'average' => TestAttempt::where('classroom_id', $classroom->id)->where('user_id', $student->id)
                ->whereNotNull('mission_id')->whereIn('status', ['submitted', 'graded'])->whereNotNull('total_score')->avg('total_score'),
            'attended' => SessionAttendance::where('user_id', $student->id)
                ->whereIn('class_session_id', $sessionIds)->where('status', 'present')->count(),
        ];
    }
}
