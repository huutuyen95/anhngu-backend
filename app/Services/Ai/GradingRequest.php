<?php

namespace App\Services\Ai;

/**
 * Một yêu cầu chấm gửi cho driver. Cố tình KHÔNG mang model Eloquent nào: driver chỉ cần
 * biết đề bài, tiêu chí và bài làm — nhờ vậy viết driver mới (Claude, Gemini…) không phải
 * đụng tới tầng dữ liệu.
 */
final class GradingRequest
{
    /**
     * Thang điểm dùng để HỎI AI. Luôn là 10 dù câu hỏi để thang nào.
     *
     * Câu tạo bằng editor có `questions.score = 1` (điểm thật được quy đổi về thang đề lúc
     * chấm), mà bảo model "cho điểm từ 0 đến 1" thì nó trả điểm rất thô. Hỏi thang 10 rồi
     * tự quy đổi vừa cho điểm mịn, vừa đúng dữ liệu.
     */
    public const AI_SCALE = 10.0;

    /**
     * @param  'writing'|'speaking'  $kind
     * @param  string|null  $answerText  Bài viết của học viên (câu writing).
     * @param  string|null  $audioPath  Đường dẫn file audio ĐÃ chuyển sang mp3/wav (câu speaking).
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $questionContent,
        public readonly ?string $hint,
        public readonly ?string $rubric,
        public readonly float $maxScore,
        public readonly ?string $answerText = null,
        public readonly ?string $audioPath = null,
        public readonly ?string $audioFormat = null,
        public readonly ?int $wordLimit = null,
    ) {}

    /** Quy điểm AI (thang 10) về thang riêng của câu, kẹp trong [0, maxScore]. */
    public function toQuestionScale(float $aiScore): float
    {
        $scaled = $aiScore / self::AI_SCALE * $this->maxScore;

        return round(max(0.0, min($this->maxScore, $scaled)), 2);
    }

    public function isSpeaking(): bool
    {
        return $this->kind === 'speaking';
    }

    /** @return array<string, string> mã tiêu chí => nhãn tiếng Việt */
    public function criteria(): array
    {
        return config("ai.criteria.{$this->kind}", []);
    }
}
