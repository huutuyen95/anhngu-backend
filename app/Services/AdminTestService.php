<?php

namespace App\Services;

use App\Models\Test;
use App\Models\TestSection;
use App\Models\User;
use App\Repositories\AdminTestRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class AdminTestService
{
    public function __construct(private readonly AdminTestRepository $tests) {}

    public function detail(Test $test): Test
    {
        return $this->tests->detail($test);
    }

    public function refresh(Test $test): Test
    {
        return $this->tests->refresh($test);
    }

    public function attemptsCount(Test $test): int
    {
        return $this->tests->attemptsCount($test);
    }

    public function updateSectionAudio(TestSection $section, string $url): void
    {
        $this->tests->updateSection($section, ['audio_url' => $url]);
    }

    public function list(array $filters, User $teacher): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? '', ['title', 'created_at', 'duration_minutes'], true)
            ? $filters['sort'] : 'created_at';
        $direction = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $page = $this->tests->paginate($filters, $teacher, $sort, $direction);
        $counts = $this->tests->questionCounts($page->getCollection()->pluck('id'));
        $page->getCollection()->each(fn (Test $test) => $test->setAttribute('question_count', $counts[$test->id] ?? 0));

        return $page;
    }

    public function create(array $data, User $teacher): Test
    {
        return $this->tests->create([
            'created_by' => $teacher->id,
            'category_id' => $data['category_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'slug' => $this->uniqueSlug($data['title']),
            'skill' => $data['skill'],
            'is_combo' => $data['is_combo'] ?? false,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? 60,
            'total_score' => $data['total_score'] ?? 10,
            'scoring_method' => $data['scoring_method'] ?? 'scale_10_even',
            'word_limit' => $data['word_limit'] ?? null,
            'rubric' => $data['rubric'] ?? null,
            'ai_grading' => $data['ai_grading'] ?? false,
            'is_published' => $data['is_published'] ?? false,
        ]);
    }

    public function update(Test $test, array $data): Test
    {
        $fields = ['title', 'description', 'category_id', 'skill', 'is_combo', 'thumbnail_url', 'duration_minutes', 'total_score', 'scoring_method', 'shuffle_questions', 'word_limit', 'rubric', 'ai_grading', 'is_published'];
        $payload = array_intersect_key($data, array_flip($fields));
        if (array_key_exists('title', $payload) && $payload['title'] !== $test->title) {
            $payload['slug'] = $this->uniqueSlug($payload['title'], $test->id);
        }

        return $this->tests->update($test, $payload);
    }

    public function delete(Test $test): void
    {
        $this->tests->delete($test);
    }

    public function duplicate(Test $test, User $teacher): Test
    {
        $title = $test->title.' (bản sao)';

        return $this->tests->duplicate($test, $teacher, $title, $this->uniqueSlug($title));
    }

    public function moveCategory(Test $test, ?int $categoryId): Test
    {
        return $this->tests->moveCategory($test, $categoryId);
    }

    public function preflight(Test $test): array
    {
        $test = $this->tests->detail($test);
        $questions = $test->parts->flatMap->sections->flatMap->questions;
        $sections = $test->parts->flatMap->sections;
        $autoTypes = ['multiple_choice', 'select', 'fill_blank'];
        $autoQuestions = $questions->filter(fn ($question) => in_array((string) $question->type->value, $autoTypes, true));
        $missingAnswer = $autoQuestions->filter(fn ($question) => $question->options->where('is_correct', true)->isEmpty());
        $missingExplanation = $questions->filter(fn ($question) => blank($question->explanation));
        $listeningSections = $sections->filter(fn ($section) => filled($section->audio_url) || $test->skill->value === 'listening');
        $sectionsNeedingAudio = $test->skill->value === 'listening' ? $sections->filter(fn ($section) => blank($section->audio_url)) : collect();
        $checks = [
            ['key' => 'answers', 'ok' => $missingAnswer->isEmpty(), 'label' => 'Đủ đáp án đúng cho câu tự chấm', 'hint' => $missingAnswer->isEmpty() ? 'Tất cả câu trắc nghiệm/điền từ đã có đáp án' : "{$missingAnswer->count()} câu chưa có đáp án đúng"],
            ['key' => 'audio', 'ok' => $sectionsNeedingAudio->isEmpty(), 'label' => 'Đã gắn audio cho phần nghe', 'hint' => $sectionsNeedingAudio->isEmpty() ? 'Không thiếu file nghe' : "{$sectionsNeedingAudio->count()} section nghe chưa có file"],
            ['key' => 'explanation', 'ok' => $missingExplanation->isEmpty(), 'label' => 'Có lời giải cho mọi câu', 'hint' => $missingExplanation->isEmpty() ? 'Đã có lời giải' : "{$missingExplanation->count()} câu chưa có lời giải"],
            ['key' => 'timing', 'ok' => (int) $test->duration_minutes > 0 && $questions->isNotEmpty(), 'label' => 'Thời gian & thang điểm hợp lệ', 'hint' => "{$test->duration_minutes} phút · thang 10 chia đều · {$questions->count()} câu"],
            ['key' => 'category', 'ok' => $test->category_id !== null, 'label' => 'Đã gán thư mục lớp', 'hint' => $test->category_id !== null ? 'Đã có thư mục' : 'Chưa gán thư mục cho đề'],
        ];

        return [
            'checks' => $checks,
            'all_ok' => collect($checks)->every(fn ($check) => $check['ok']),
            'question_count' => $questions->count(),
            'has_listening' => $listeningSections->isNotEmpty(),
        ];
    }

    public function saveStructure(Test $test, array $data): Test
    {
        return $this->tests->saveStructure($test, $data, (int) setting('content.listening_max_plays', 2));
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'de-thi';
        $slug = $base;
        $index = 1;
        while ($this->tests->slugExists($slug, $ignoreId)) {
            $slug = $base.'-'.(++$index);
        }

        return $slug;
    }
}
