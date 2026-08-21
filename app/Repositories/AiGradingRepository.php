<?php

namespace App\Repositories;

use App\Enums\QuestionType;
use App\Models\AiUsageLog;
use App\Models\AttemptAiSuggestion;
use App\Models\Question;
use App\Models\Test;
use App\Models\TestAttempt;
use Illuminate\Support\Collection;

/**
 * Truy cập dữ liệu cho phần chấm bằng AI. Service chỉ gọi qua đây, không tự query model —
 * theo luật kiến trúc đã khoá bằng Tests\Architecture\ApiLayeringTest.
 */
class AiGradingRepository
{
    /** Đề kèm toàn bộ cây câu hỏi để lọc ra câu viết/nói. */
    public function testWithQuestions(TestAttempt $attempt): ?Test
    {
        return $attempt->test()->with('parts.sections.questions')->first();
    }

    /** Câu trả lời của lượt, đánh khoá theo question_id. */
    public function answersByQuestion(TestAttempt $attempt): Collection
    {
        return $attempt->answers()->get()->keyBy('question_id');
    }

    /**
     * Câu cần AI chấm — đúng những loại cô phải chấm tay.
     *
     * @return Collection<int, Question>
     */
    public function gradableQuestions(Test $test): Collection
    {
        return $test->parts
            ->flatMap(fn ($part) => $part->sections)
            ->flatMap(fn ($section) => $section->questions)
            ->filter(fn (Question $q) => in_array($q->type, [QuestionType::Writing, QuestionType::Speaking], true))
            ->values();
    }

    /** @param  array<string, mixed>  $attributes */
    public function saveSuggestion(TestAttempt $attempt, Question $question, array $attributes): void
    {
        AttemptAiSuggestion::updateOrCreate(
            ['test_attempt_id' => $attempt->id, 'question_id' => $question->id],
            $attributes,
        );
    }

    /** @param  array<string, mixed>  $attributes */
    public function logUsage(array $attributes): void
    {
        AiUsageLog::create($attributes);
    }

    /** Tổng chi phí đã dùng trong tháng dương lịch hiện tại (USD). */
    public function spentThisMonth(): float
    {
        return (float) AiUsageLog::where('created_at', '>=', now()->startOfMonth())->sum('cost_usd');
    }
}
