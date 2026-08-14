<?php

use App\Models\TestAttempt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đồng hồ làm bài tạm dừng được.
 *
 * Trước đây hạn nộp là mốc tuyệt đối (started_at + thời lượng) nên học viên thoát ra
 * thì đồng hồ vẫn chạy, quay lại có khi đã hết giờ. Giờ lưu SỐ GIÂY CÒN LẠI:
 *   - `resumed_at != null` → đồng hồ đang chạy, hết giờ lúc `resumed_at + remaining_seconds`
 *   - `resumed_at = null`  → đang tạm dừng, còn đúng `remaining_seconds` giây
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_attempts', function (Blueprint $table) {
            $table->unsignedInteger('remaining_seconds')->nullable()->after('tab_exit_count');
            $table->timestamp('resumed_at')->nullable()->after('remaining_seconds');
        });

        // Lượt đang làm dở: chốt phần thời gian còn lại theo cách tính cũ rồi cho chạy tiếp,
        // để học viên đang thi giữa chừng không bị mất/được thêm giờ.
        TestAttempt::query()
            ->where('status', 'in_progress')
            ->whereNotNull('started_at')
            ->with('test:id,duration_minutes')
            ->chunkById(200, function ($attempts) {
                foreach ($attempts as $attempt) {
                    $duration = (int) ($attempt->test?->duration_minutes ?? 0);
                    if ($duration <= 0) {
                        continue; // đề không giới hạn giờ — không cần đồng hồ
                    }

                    $elapsed = now()->getTimestamp() - $attempt->started_at->getTimestamp();

                    $attempt->forceFill([
                        'remaining_seconds' => max(0, $duration * 60 - $elapsed),
                        'resumed_at' => now(),
                    ])->save();
                }
            });
    }

    public function down(): void
    {
        Schema::table('test_attempts', function (Blueprint $table) {
            $table->dropColumn(['remaining_seconds', 'resumed_at']);
        });
    }
};
