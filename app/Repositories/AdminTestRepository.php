<?php

namespace App\Repositories;

use App\Enums\UserRole;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Test;
use App\Models\TestPart;
use App\Models\TestSection;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminTestRepository
{
    public function refresh(Test $test): Test
    {
        return $test->fresh();
    }

    public function detail(Test $test): Test
    {
        return $test->load([
            'parts' => fn ($query) => $query->orderBy('order'),
            'parts.sections' => fn ($query) => $query->orderBy('order'),
            'parts.sections.questions' => fn ($query) => $query->orderBy('order'),
            'parts.sections.questions.options',
        ]);
    }

    public function attemptsCount(Test $test): int
    {
        return $test->attempts()->count();
    }

    public function updateSection(TestSection $section, array $data): void
    {
        $section->update($data);
    }

    public function paginate(array $filters, User $teacher, string $sort, string $direction): LengthAwarePaginator
    {
        return Test::query()
            ->with('category:id,name')
            ->withCount('attempts')
            ->when($teacher->role !== UserRole::Admin, fn ($query) => $query->where('created_by', $teacher->id))
            ->when($filters['q'] ?? null, fn ($query, $value) => $query->where('title', 'like', "%{$value}%"))
            ->when($filters['skill'] ?? null, fn ($query, $value) => $query->where('skill', $value))
            ->when($filters['format'] ?? null, fn ($query, $value) => $query->where('format', $value))
            ->when(($filters['category_id'] ?? null) !== null && ($filters['category_id'] ?? '') !== '', fn ($query) => $query->where('category_id', (int) $filters['category_id']))
            ->when(array_key_exists('is_published', $filters) && $filters['is_published'] !== null && $filters['is_published'] !== '', fn ($query) => $query->where('is_published', filter_var($filters['is_published'], FILTER_VALIDATE_BOOLEAN)))
            ->orderBy($sort, $direction)
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    public function questionCounts(Collection $testIds): Collection
    {
        return Question::countsByTest($testIds);
    }

    public function create(array $data): Test
    {
        return Test::create($data);
    }

    public function update(Test $test, array $data): Test
    {
        $test->update($data);

        return $test;
    }

    public function delete(Test $test): void
    {
        $test->delete();
    }

    public function duplicate(Test $test, User $teacher, string $title, string $slug): Test
    {
        return DB::transaction(function () use ($test, $teacher, $title, $slug): Test {
            $copy = $test->replicate(['slug']);
            $copy->created_by = $teacher->id;
            $copy->title = $title;
            $copy->slug = $slug;
            $copy->is_published = false;
            $copy->save();
            $test->load('parts.sections.questions.options');

            foreach ($test->parts as $part) {
                $newPart = $part->replicate(['test_id']);
                $newPart->test_id = $copy->id;
                $newPart->save();
                foreach ($part->sections as $section) {
                    $newSection = $section->replicate(['test_part_id']);
                    $newSection->test_part_id = $newPart->id;
                    $newSection->save();
                    foreach ($section->questions as $question) {
                        $newQuestion = $question->replicate(['test_section_id']);
                        $newQuestion->test_section_id = $newSection->id;
                        $newQuestion->save();
                        foreach ($question->options as $option) {
                            $newOption = $option->replicate(['question_id']);
                            $newOption->question_id = $newQuestion->id;
                            $newOption->save();
                        }
                    }
                }
            }

            $copy->setAttribute('question_count', $copy->questionCount());

            return $copy;
        });
    }

    public function moveCategory(Test $test, ?int $categoryId): Test
    {
        $test->update(['category_id' => $categoryId]);

        return $test->fresh(['category']);
    }

    public function saveStructure(Test $test, array $data, int $defaultMaxPlays): Test
    {
        DB::transaction(function () use ($test, $data, $defaultMaxPlays): void {
            $seenPartIds = [];
            foreach ($data['parts'] as $partData) {
                $part = $this->syncPart($test, $partData);
                $seenPartIds[] = $part->id;
                $seenSectionIds = [];
                foreach ($partData['sections'] as $sectionData) {
                    $section = $this->syncSection($part, $sectionData, $defaultMaxPlays);
                    $seenSectionIds[] = $section->id;
                    $seenQuestionIds = [];
                    foreach ($sectionData['questions'] as $questionData) {
                        $question = $this->syncQuestion($section, $questionData);
                        $seenQuestionIds[] = $question->id;
                        $seenOptionIds = [];
                        foreach ($questionData['options'] ?? [] as $optionData) {
                            $seenOptionIds[] = $this->syncOption($question, $optionData)->id;
                        }
                        $question->options()->whereNotIn('id', $seenOptionIds)->delete();
                    }
                    $section->questions()->whereNotIn('id', $seenQuestionIds)->delete();
                }
                $part->sections()->whereNotIn('id', $seenSectionIds)->delete();
            }
            $test->parts()->whereNotIn('id', $seenPartIds)->delete();
        });

        return $this->detail($test);
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        return Test::where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    private function syncPart(Test $test, array $data): TestPart
    {
        $part = isset($data['id']) ? $test->parts()->find($data['id']) : null;
        $attributes = ['order' => $data['order'], 'title' => $data['title'], 'display_mode' => $data['display_mode'] ?? 'default', 'image_url' => $data['image_url'] ?? null];
        if ($part) {
            $part->update($attributes);

            return $part;
        }

        return $test->parts()->create($attributes);
    }

    private function syncSection(TestPart $part, array $data, int $defaultMaxPlays): TestSection
    {
        $section = isset($data['id']) ? $part->sections()->find($data['id']) : null;
        $attributes = [
            'order' => $data['order'],
            'instruction' => $data['instruction'] ?? null,
            'passage' => $data['passage'] ?? null,
            'audio_url' => $data['audio_url'] ?? null,
            'max_plays' => $data['max_plays'] ?? (($data['audio_url'] ?? null) ? $defaultMaxPlays : null),
        ];
        if ($section) {
            $section->update($attributes);

            return $section;
        }

        return $part->sections()->create($attributes);
    }

    private function syncQuestion(TestSection $section, array $data): Question
    {
        $question = isset($data['id']) ? $section->questions()->find($data['id']) : null;
        $attributes = [
            'order' => $data['order'], 'type' => $data['type'], 'content' => $data['content'] ?? null,
            'hint' => $data['hint'] ?? null,
            'audio_url' => $data['audio_url'] ?? null, 'images' => $data['images'] ?? null,
            'record_limit_seconds' => $data['record_limit_seconds'] ?? null,
            'explanation' => $data['explanation'] ?? null, 'score' => $data['score'] ?? 1,
        ];
        if ($question) {
            $question->update($attributes);

            return $question;
        }

        return $section->questions()->create($attributes);
    }

    private function syncOption(Question $question, array $data): QuestionOption
    {
        $option = isset($data['id']) ? $question->options()->find($data['id']) : null;
        $attributes = ['label' => $data['label'] ?? null, 'content' => $data['content'], 'is_correct' => $data['is_correct'] ?? false];
        if ($option) {
            $option->update($attributes);

            return $option;
        }

        return $question->options()->create($attributes);
    }
}
