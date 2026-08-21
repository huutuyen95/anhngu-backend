<?php

namespace App\Repositories;

use App\Enums\Skill;
use App\Models\ActivityLog;
use App\Models\AttemptAnswer;
use App\Models\Mission;
use App\Models\Question;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttemptRepository
{
    public function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }

    public function saveAnswers(TestAttempt $a, array $answers): void
    {
        foreach ($answers as $x) {
            AttemptAnswer::updateOrCreate(['test_attempt_id' => $a->id, 'question_id' => $x['question_id']], ['question_option_id' => $x['question_option_id'] ?? null, 'answer_text' => $x['answer_text'] ?? null]);
        }
    }

    /**
     * Số lượt đề NÓI em đã tự mở hôm nay (không tính bài cô giao) — dùng cho hạn mức
     * `content.speaking_attempts_per_day`.
     */
    public function speakingAttemptsToday(User $student): int
    {
        return TestAttempt::query()
            ->where('user_id', $student->id)
            ->whereNull('mission_id')
            ->whereDate('started_at', now()->toDateString())
            ->whereHas('test', fn ($q) => $q->where('skill', Skill::Speaking->value))
            ->count();
    }

    public function mission(TestAttempt $a): mixed
    {
        return $a->mission()->with(['classroom:id,name', 'classSession:id,title,order'])->first();
    }

    public function loadResult(TestAttempt $a): TestAttempt
    {
        return $a->load('answers.gradedBy:id,name');
    }

    public function resultTest(TestAttempt $a): Test
    {
        return $a->test()->with(['parts' => fn ($q) => $q->orderBy('order'), 'parts.sections' => fn ($q) => $q->orderBy('order'), 'parts.sections.questions' => fn ($q) => $q->orderBy('order'), 'parts.sections.questions.options'])->firstOrFail();
    }

    public function loadState(TestAttempt $a): TestAttempt
    {
        return $a->load(['test:id,duration_minutes', 'answers']);
    }

    public function pause(TestAttempt $a): void
    {
        $a->load('test:id,duration_minutes');
        if ($a->status === 'in_progress') {
            $a->pauseClock();
        }
    }

    public function resume(TestAttempt $a): void
    {
        $a->load('test:id,duration_minutes');
        if ($a->status === 'in_progress') {
            $a->resumeClock();
        }
    }

    public function incrementTabExit(TestAttempt $a): TestAttempt
    {
        $a->increment('tab_exit_count');

        return $a->refresh();
    }

    public function answer(TestAttempt $attempt, Question $question): ?AttemptAnswer
    {
        return AttemptAnswer::where('test_attempt_id', $attempt->id)
            ->where('question_id', $question->id)
            ->first();
    }

    public function upsertAudioAnswer(TestAttempt $attempt, Question $question, ?string $url): AttemptAnswer
    {
        return AttemptAnswer::updateOrCreate(
            ['test_attempt_id' => $attempt->id, 'question_id' => $question->id],
            ['answer_file_url' => $url]
        );
    }

    public function questionBelongsToTest(Question $question, TestAttempt $attempt): bool
    {
        return Question::whereKey($question->id)
            ->whereHas('section.part', fn ($query) => $query->where('test_id', $attempt->test_id))
            ->exists();
    }

    public function staleAttempt(User $student, Test $test, ?Mission $mission): ?TestAttempt
    {
        return TestAttempt::query()
            ->where('user_id', $student->id)
            ->where('test_id', $test->id)
            ->where('status', 'in_progress')
            ->when($mission, fn ($query) => $query->where('mission_id', $mission->id), fn ($query) => $query->whereNull('mission_id'))
            ->first();
    }

    public function deleteAttemptWithAnswers(TestAttempt $attempt): void
    {
        $attempt->answers()->delete();
        $attempt->delete();
    }

    public function create(array $data): TestAttempt
    {
        return TestAttempt::create($data);
    }

    public function findMission(User $student, Test $test, int $missionId): ?Mission
    {
        return Mission::query()
            ->whereKey($missionId)
            ->where('user_id', $student->id)
            ->where('missionable_type', $test->getMorphClass())
            ->where('missionable_id', $test->id)
            ->first();
    }

    public function missionHasAttemptsLeft(Mission $mission): bool
    {
        return $mission->hasAttemptsLeft();
    }

    public function missionAttemptsUsed(Mission $mission): int
    {
        return $mission->attemptsUsed();
    }

    public function questionCount(Test $test): int
    {
        return $test->questionCount();
    }

    public function paginateForGrading(array $filters): LengthAwarePaginator
    {
        return TestAttempt::query()
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['classroom_id'] ?? null, fn ($query, $value) => $query->where('classroom_id', $value))
            ->when($filters['test_id'] ?? null, fn ($query, $value) => $query->where('test_id', $value))
            ->when($filters['user_id'] ?? null, fn ($query, $value) => $query->where('user_id', $value))
            ->when($filters['source'] ?? null, fn ($query, $value) => $query->where('source', $value))
            ->with(['user:id,name,email', 'test:id,title,skill', 'classroom:id,name'])
            ->orderByDesc('submitted_at')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    public function loadForGrading(TestAttempt $attempt): TestAttempt
    {
        return $attempt->load([
            'user:id,name,email',
            'classroom:id,name',
            'test',
            'test.parts' => fn ($query) => $query->orderBy('order'),
            'test.parts.sections' => fn ($query) => $query->orderBy('order'),
            'test.parts.sections.questions' => fn ($query) => $query->orderBy('order'),
            'test.parts.sections.questions.options',
            'answers.gradedBy:id,name',
            // Gợi ý chấm của AI — chỉ nạp ở khu cô chấm, học viên không bao giờ thấy.
            'aiSuggestions',
        ]);
    }

    public function questions(Collection $ids): Collection
    {
        return Question::whereIn('id', $ids)->get()->keyBy('id');
    }

    public function gradeAnswer(TestAttempt $attempt, Question $question, array $data): void
    {
        AttemptAnswer::updateOrCreate(
            ['test_attempt_id' => $attempt->id, 'question_id' => $question->id],
            $data
        );
    }

    public function correctAnswerCount(TestAttempt $attempt): int
    {
        return $attempt->answers()->where('is_correct', true)->count();
    }

    public function gradingTest(TestAttempt $attempt): Test
    {
        return $attempt->test()->with('parts.sections.questions')->firstOrFail();
    }

    public function rawScore(TestAttempt $attempt): float
    {
        return (float) $attempt->answers()->sum('score');
    }

    public function submissionTest(TestAttempt $attempt): Test
    {
        return $attempt->test()->with('parts.sections.questions.options')->firstOrFail();
    }

    public function answersByQuestion(TestAttempt $attempt): Collection
    {
        return $attempt->answers()->get()->keyBy('question_id');
    }

    public function upsertGradedAnswer(TestAttempt $attempt, Question $question, array $data): AttemptAnswer
    {
        return AttemptAnswer::updateOrCreate(
            ['test_attempt_id' => $attempt->id, 'question_id' => $question->id],
            $data
        );
    }

    public function logActivity(array $data): void
    {
        ActivityLog::create($data);
    }

    public function missionWithClassroom(TestAttempt $attempt): ?Mission
    {
        return $attempt->mission()->with('classroom')->first();
    }

    public function updateMission(Mission $mission, array $data): void
    {
        $mission->update($data);
    }

    public function updateAttempt(TestAttempt $attempt, array $data): void
    {
        $attempt->update($data);
    }

    public function previousBest(TestAttempt $attempt): ?TestAttempt
    {
        return TestAttempt::whereIn('status', ['submitted', 'graded'])
            ->sameScope($attempt)
            ->whereKeyNot($attempt->id)
            ->lockForUpdate()
            ->first();
    }
}
