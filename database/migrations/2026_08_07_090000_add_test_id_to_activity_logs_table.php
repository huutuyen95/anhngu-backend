<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            // Khoá thật tới đề, thay cho việc dò theo `subject` (tiêu đề đề — đổi tên là hỏng).
            // activity_logs giữ MỌI lượt nộp, còn test_attempts bị dedup xoá bớt (chỉ giữ lượt
            // điểm cao nhất) → chỉ bảng này tính được điểm TB / tổng lượt làm thật của một đề.
            $table->foreignId('test_id')->nullable()->after('user_id')
                ->constrained('tests')->nullOnDelete();

            $table->index(['test_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['test_id', 'type']);
            $table->dropConstrainedForeignId('test_id');
        });
    }
};
