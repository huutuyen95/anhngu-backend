<?php

namespace App\Repositories;

use App\Models\Question;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StudentTestRepository
{
    private const FINALIZED_STATUSES = ['submitted', 'graded'];

    public function detail(Test $test): Test
    {
        return $test->load([
            'parts' => fn ($query) => $query->orderBy('order'),
            'parts.sections' => fn ($query) => $query->orderBy('order'),
            'parts.sections.questions' => fn ($query) => $query->orderBy('order'),
            'parts.sections.questions.options',
        ]);
    }

    public function paginate(array $filters, User $student, Collection $buckets, int $perPage, string $sort): LengthAwarePaginator
    {
        $query = $this->baseQuery($filters)
            ->with(['category:id,name,parent_id,classroom_id', 'category.parent:id,name', 'category.classroom:id,name'])
            ->withCount(['activityLogs as attempts_total' => fn ($query) => $query->where('type', 'test_attempt')])
            ->withAvg(['activityLogs as avg_score' => fn ($query) => $query->where('type', 'test_attempt')], 'score');

        $this->applyStatusFilter($query, $buckets, $student);
        match ($sort) {
            'name' => $query->orderBy('title'),
            'popular' => $query->orderByDesc('attempts_total'),
            default => $query->orderByDesc('created_at'),
        };

        return $query->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    public function matchingIds(array $filters): Collection
    {
        return $this->baseQuery($filters)->pluck('id');
    }

    public function newThisWeek(array $filters): int
    {
        return $this->baseQuery($filters)->where('created_at', '>=', now()->subWeek())->count();
    }

    public function questionCounts(Collection $testIds): Collection
    {
        return Question::countsByTest($testIds);
    }

    public function attempts(User $student, Collection $testIds, array $statuses): Collection
    {
        if ($testIds->isEmpty()) {
            return collect();
        }

        return TestAttempt::query()
            ->where('user_id', $student->id)
            ->whereNull('mission_id')
            ->whereIn('status', $statuses)
            ->whereIn('test_id', $testIds)
            ->withCount(['answers as answered_count' => fn ($query) => $query->where(
                fn ($query) => $query->whereNotNull('question_option_id')
                    ->orWhere(fn ($query) => $query->whereNotNull('answer_text')->where('answer_text', '!=', ''))
                    ->orWhereNotNull('answer_file_url')
            )])
            ->get()
            ->groupBy('test_id');
    }

    private function baseQuery(array $filters): Builder
    {
        return Test::query()
            ->where('is_published', true)
            ->when($filters['q'] ?? null, fn ($query, $value) => $query->where('title', 'like', '%'.$value.'%'))
            ->when($filters['skill'] ?? null, fn ($query, $value) => $query->where('skill', $value));
    }

    private function applyStatusFilter(Builder $query, Collection $buckets, User $student): void
    {
        if ($buckets->isEmpty()) {
            return;
        }
        $query->where(function (Builder $query) use ($buckets, $student): void {
            foreach ($buckets as $bucket) {
                $query->orWhere(fn (Builder $nested) => $this->whereBucket($nested, $bucket, $student));
            }
        });
    }

    private function whereBucket(Builder $query, string $bucket, User $student): void
    {
        $mine = fn (array $statuses) => fn ($attempts) => $attempts
            ->where('user_id', $student->id)->whereNull('mission_id')->whereIn('status', $statuses);

        match ($bucket) {
            'todo' => $query->whereDoesntHave('attempts', fn ($attempts) => $attempts->where('user_id', $student->id)->whereNull('mission_id')),
            'doing' => $query->whereHas('attempts', $mine(['in_progress'])),
            'grading' => $query->whereHas('attempts', $mine(['pending_review']))->whereDoesntHave('attempts', $mine(['in_progress'])),
            'done' => $query->whereHas('attempts', $mine(self::FINALIZED_STATUSES))->whereDoesntHave('attempts', $mine(['in_progress', 'pending_review'])),
        };
    }
}
