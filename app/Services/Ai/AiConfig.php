<?php

namespace App\Services\Ai;

use App\Repositories\AiGradingRepository;

/**
 * Gom mọi câu hỏi "cấu hình AI đang thế nào" về một chỗ.
 *
 * Khoá API đọc từ khu Cài đặt TRƯỚC (cô tự dán, được mã hoá), không có thì mới lấy env —
 * nhờ vậy lúc bàn giao cô chỉ cần vào Cài đặt dán khoá, không phải nhờ ai sửa file server.
 */
final class AiConfig
{
    public function __construct(private readonly AiGradingRepository $repository) {}

    public function enabled(): bool
    {
        return (bool) setting('ai.enabled', false);
    }

    public function provider(): string
    {
        return (string) setting('ai.provider', 'openai');
    }

    public function apiKey(): ?string
    {
        $fromSettings = setting('ai.api_key');

        return filled($fromSettings) ? (string) $fromSettings : config('ai.openai.api_key');
    }

    public function textModel(): string
    {
        return (string) setting('ai.text_model', 'gpt-5.4-mini');
    }

    public function audioModel(): string
    {
        return (string) setting('ai.audio_model', 'gpt-audio');
    }

    public function gradesWriting(): bool
    {
        return (bool) setting('ai.grade_writing', true);
    }

    public function gradesSpeaking(): bool
    {
        return (bool) setting('ai.grade_speaking', true);
    }

    public function monthlyBudgetUsd(): float
    {
        return (float) setting('ai.monthly_budget_usd', 15.0);
    }

    /** Đã tiêu hết hạn mức tháng này chưa. Hết → ngừng gọi AI, bài về chờ cô chấm tay. */
    public function budgetExhausted(): bool
    {
        $budget = $this->monthlyBudgetUsd();

        return $budget > 0 && $this->repository->spentThisMonth() >= $budget;
    }

    /**
     * Ước tính chi phí một lần gọi theo bảng giá ở config/ai.php.
     *
     * Chỉ để cảnh báo sớm khi sắp chạm hạn mức — hoá đơn thật vẫn là của nhà cung cấp.
     */
    public function estimateCost(GradingResult $result): float
    {
        $price = config("ai.pricing.{$result->model}", config('ai.fallback_pricing'));

        // Token audio nằm TRONG prompt_tokens, phải tách ra vì đơn giá khác hẳn.
        $textInput = max(0, $result->inputTokens - $result->audioInputTokens);

        $cost = $textInput / 1_000_000 * (float) ($price['input'] ?? 0)
            + $result->outputTokens / 1_000_000 * (float) ($price['output'] ?? 0)
            + $result->audioInputTokens / 1_000_000 * (float) ($price['audio_input'] ?? 0);

        return round($cost, 6);
    }
}
