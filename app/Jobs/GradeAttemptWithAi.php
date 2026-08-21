<?php

namespace App\Jobs;

use App\Models\TestAttempt;
use App\Services\Ai\AiGradingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Chấm AI chạy NGOÀI request nộp bài: gọi API mất vài giây (câu nói còn lâu hơn vì phải
 * chuyển định dạng rồi tải audio lên), để trong request thì học viên ngồi đợi vô lý.
 *
 * Job hỏng cũng không sao — lượt làm bài vẫn ở `pending_review` và cô chấm tay như cũ.
 */
class GradeAttemptWithAi implements ShouldQueue
{
    use Queueable;

    /** Thử lại 2 lần rồi thôi: lỗi thường là hết hạn mức hoặc khoá sai, thử mãi vô ích. */
    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public readonly int $attemptId) {}

    public function handle(AiGradingService $grading): void
    {
        $attempt = TestAttempt::with('test')->find($this->attemptId);

        // Lượt có thể đã bị dedup xoá giữa chừng — không có thì thôi.
        if ($attempt) {
            $grading->gradeAttempt($attempt);
        }
    }
}
