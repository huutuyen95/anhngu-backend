<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tách tiến độ học từ vựng theo lớp: học trong lớp ghi classroom_id = lớp,
     * tự luyện Thư viện ghi NULL. Hai luồng không đè nhau (giống dedup lượt đề theo mission).
     *
     * Thứ tự quan trọng trên MySQL: unique cũ (user_id,card_id) đang đỡ khóa ngoại user_id
     * (không có index user_id riêng). Phải tạo composite mới (cũng bắt đầu bằng user_id) TRƯỚC,
     * rồi mới drop unique cũ — nếu drop trước sẽ lỗi 1553 "needed in a foreign key constraint".
     */
    public function up(): void
    {
        Schema::table('card_progress', function (Blueprint $table) {
            $table->foreignId('classroom_id')->nullable()->after('card_id')->constrained()->nullOnDelete();
            $table->unique(['user_id', 'card_id', 'classroom_id']);
        });

        Schema::table('card_progress', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'card_id']);
        });
    }

    public function down(): void
    {
        // Khôi phục index đỡ FK trước khi drop composite.
        Schema::table('card_progress', function (Blueprint $table) {
            $table->unique(['user_id', 'card_id']);
        });

        Schema::table('card_progress', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'card_id', 'classroom_id']);
            $table->dropConstrainedForeignId('classroom_id');
        });
    }
};
