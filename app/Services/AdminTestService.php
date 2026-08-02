<?php

namespace App\Services;

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
use Illuminate\Support\Str;

class AdminTestService
{
    /**
     * Danh sách đề của giáo viên (kèm cả nháp), lọc / tìm / phân trang.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, User $teacher): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? '', ['title', 'created_at', 'duration_minutes'], true)
            ? $filters['sort'] : 'created_at';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $page = Test::query()
            ->when($teacher->role !== UserRole::Admin, fn ($q) => $q->where('created_by', $teacher->id))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('title', 'like', "%{$term}%"))
            ->when($filters['skill'] ?? null, fn ($q, $skill) => $q->where('skill', $skill))
            ->when(
                array_key_exists('is_published', $filters) && $filters['is_published'] !== null && $filters['is_published'] !== '',
                fn ($q) => $q->where('is_published', filter_var($filters['is_published'], FILTER_VALIDATE_BOOLEAN))
            )
            ->orderBy($sort, $dir)
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        $counts = $this->questionCounts($page->getCollection()->pluck('id'));
        $page->getCollection()->each(
            fn (Test $test) => $test->setAttribute('question_count', $counts[$test->id] ?? 0)
        );

        return $page;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $teacher): Test
    {
        return Test::create([
            'created_by' => $teacher->id,
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['title']),
            'skill' => $data['skill'],
            'is_combo' => $data['is_combo'] ?? false,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? 60,
            'total_score' => $data['total_score'] ?? 10,
            'scoring_method' => $data['scoring_method'] ?? 'by_correct_count',
            'word_limit' => $data['word_limit'] ?? null,
            'rubric' => $data['rubric'] ?? null,
            'is_published' => $data['is_published'] ?? false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Test $test, array $data): Test
    {
        $fields = [
            'title', 'skill', 'is_combo', 'thumbnail_url', 'duration_minutes',
            'total_score', 'scoring_method', 'word_limit', 'rubric', 'is_published',
        ];
        $payload = array_intersect_key($data, array_flip($fields));

        if (array_key_exists('title', $payload) && $payload['title'] !== $test->title) {
            $payload['slug'] = $this->uniqueSlug($payload['title'], $test->id);
        }

        $test->update($payload);

        return $test;
    }

    public function delete(Test $test): void
    {
        $test->delete();
    }

    /**
     * Đồng bộ toàn bộ cây Part → Section → Question → Option theo id gửi lên.
     * Item có id thuộc đúng cha hiện tại → cập nhật; không có id (hoặc id lạ) → tạo mới;
     * item cũ không được gửi lên → xoá (cascade DB dọn luôn cấp con).
     *
     * @param  array<string, mixed>  $data
     */
    public function saveStructure(Test $test, array $data): Test
    {
        DB::transaction(function () use ($test, $data) {
            $seenPartIds = [];

            foreach ($data['parts'] as $partData) {
                $part = $this->syncPart($test, $partData);
                $seenPartIds[] = $part->id;

                $seenSectionIds = [];
                foreach ($partData['sections'] as $sectionData) {
                    $section = $this->syncSection($part, $sectionData);
                    $seenSectionIds[] = $section->id;

                    $seenQuestionIds = [];
                    foreach ($sectionData['questions'] as $questionData) {
                        $question = $this->syncQuestion($section, $questionData);
                        $seenQuestionIds[] = $question->id;

                        $seenOptionIds = [];
                        foreach ($questionData['options'] ?? [] as $optionData) {
                            $option = $this->syncOption($question, $optionData);
                            $seenOptionIds[] = $option->id;
                        }
                        $question->options()->whereNotIn('id', $seenOptionIds)->delete();
                    }
                    $section->questions()->whereNotIn('id', $seenQuestionIds)->delete();
                }
                $part->sections()->whereNotIn('id', $seenSectionIds)->delete();
            }
            $test->parts()->whereNotIn('id', $seenPartIds)->delete();
        });

        return $test->load([
            'parts' => fn ($q) => $q->orderBy('order'),
            'parts.sections' => fn ($q) => $q->orderBy('order'),
            'parts.sections.questions' => fn ($q) => $q->orderBy('order'),
            'parts.sections.questions.options',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncPart(Test $test, array $data): TestPart
    {
        $part = isset($data['id']) ? $test->parts()->find($data['id']) : null;
        $attrs = [
            'order' => $data['order'],
            'title' => $data['title'],
            'display_mode' => $data['display_mode'] ?? 'default',
            'image_url' => $data['image_url'] ?? null,
        ];

        if ($part) {
            $part->update($attrs);

            return $part;
        }

        return $test->parts()->create($attrs);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncSection(TestPart $part, array $data): TestSection
    {
        $section = isset($data['id']) ? $part->sections()->find($data['id']) : null;
        $attrs = [
            'order' => $data['order'],
            'instruction' => $data['instruction'] ?? null,
            'passage' => $data['passage'] ?? null,
            'audio_url' => $data['audio_url'] ?? null,
            'max_plays' => $data['max_plays'] ?? null,
        ];

        if ($section) {
            $section->update($attrs);

            return $section;
        }

        return $part->sections()->create($attrs);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncQuestion(TestSection $section, array $data): Question
    {
        $question = isset($data['id']) ? $section->questions()->find($data['id']) : null;
        $attrs = [
            'order' => $data['order'],
            'type' => $data['type'],
            'content' => $data['content'] ?? null,
            'audio_url' => $data['audio_url'] ?? null,
            'images' => $data['images'] ?? null,
            'record_limit_seconds' => $data['record_limit_seconds'] ?? null,
            'explanation' => $data['explanation'] ?? null,
            'score' => $data['score'] ?? 1,
        ];

        if ($question) {
            $question->update($attrs);

            return $question;
        }

        return $section->questions()->create($attrs);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncOption(Question $question, array $data): QuestionOption
    {
        $option = isset($data['id']) ? $question->options()->find($data['id']) : null;
        $attrs = [
            'label' => $data['label'] ?? null,
            'content' => $data['content'],
            'is_correct' => $data['is_correct'] ?? false,
        ];

        if ($option) {
            $option->update($attrs);

            return $option;
        }

        return $question->options()->create($attrs);
    }

    /**
     * @return Collection<int, int>
     */
    private function questionCounts(Collection $testIds): Collection
    {
        if ($testIds->isEmpty()) {
            return collect();
        }

        return Question::query()
            ->join('test_sections', 'questions.test_section_id', '=', 'test_sections.id')
            ->join('test_parts', 'test_sections.test_part_id', '=', 'test_parts.id')
            ->whereIn('test_parts.test_id', $testIds)
            ->selectRaw('test_parts.test_id as test_id, count(*) as question_count')
            ->groupBy('test_parts.test_id')
            ->pluck('question_count', 'test_id');
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'de-thi';
        $slug = $base;
        $i = 1;

        while (Test::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = "{$base}-".(++$i);
        }

        return $slug;
    }
}
