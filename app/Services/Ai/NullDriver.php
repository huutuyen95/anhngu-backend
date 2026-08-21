<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Driver "không làm gì" — dùng khi chưa cấu hình nhà cung cấp nào, hoặc cô chọn một nhà
 * cung cấp chưa được cài.
 *
 * Đây là thứ giữ cho hệ thống chạy y như cũ khi chưa có khoá API: `isReady()` trả `false`
 * nên AiGradingService dừng ngay từ cổng, không gọi mạng, không ghi log lỗi, bài viết/nói
 * vẫn vào hàng chờ cô chấm tay đúng như trước khi có tính năng này.
 */
final class NullDriver implements GradingDriver
{
    public function name(): string
    {
        return 'none';
    }

    public function isReady(): bool
    {
        return false;
    }

    public function supportsAudio(): bool
    {
        return false;
    }

    public function grade(GradingRequest $request): GradingResult
    {
        throw new RuntimeException('Chưa cấu hình dịch vụ chấm AI.');
    }
}
