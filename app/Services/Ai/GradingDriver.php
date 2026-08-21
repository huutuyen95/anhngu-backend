<?php

namespace App\Services\Ai;

/**
 * Hợp đồng chung cho mọi nhà cung cấp AI chấm bài.
 *
 * Thêm nhà cung cấp mới (Claude, Gemini…) = viết một class implement interface này rồi khai
 * trong AiDriverManager + thêm một lựa chọn ở `ai.provider` (config/appsettings.php).
 * KHÔNG phải đụng vào luồng nộp bài, màn chấm hay bảng dữ liệu.
 */
interface GradingDriver
{
    /** Mã nhà cung cấp, lưu vào `attempt_ai_suggestions.provider`. */
    public function name(): string;

    /**
     * Đã đủ điều kiện gọi chưa (có khoá API…). `false` thì AiGradingService bỏ qua hoàn
     * toàn — bài về hàng chờ cô chấm tay, không lỗi, không cảnh báo cho học viên.
     */
    public function isReady(): bool;

    /** Có nghe được audio không. `false` thì câu nói bỏ qua, câu viết vẫn chấm. */
    public function supportsAudio(): bool;

    /**
     * @throws \RuntimeException khi gọi hỏng hoặc phản hồi sai định dạng.
     */
    public function grade(GradingRequest $request): GradingResult;
}
