<?php

namespace App\Services\Ai;

/**
 * Kết quả một lần chấm. `feedback` đã được ghép sẵn thành văn bản tiếng Việt để cô dán
 * thẳng vào ô nhận xét — driver không cần biết cô sẽ hiển thị ra sao.
 */
final class GradingResult
{
    /**
     * @param  array<string, float>  $criteria  mã tiêu chí => điểm
     */
    public function __construct(
        public readonly float $score,
        public readonly string $feedback,
        public readonly array $criteria,
        public readonly string $model,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly int $audioInputTokens = 0,
        public readonly string $raw = '',
    ) {}
}
