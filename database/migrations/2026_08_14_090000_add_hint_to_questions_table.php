<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gợi ý hiển thị CHO HỌC VIÊN ngay lúc làm bài — chủ yếu cho câu speaking
     * ("You should say: …" đi kèm bộ ảnh gợi ý ở `questions.images`).
     *
     * Không dùng lại `explanation` được: đó là lời giải, `TestDetailResource` chỉ đưa ra khi
     * `revealAnswers = true` (sau khi nộp), nên học viên không bao giờ thấy lúc đang làm.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->text('hint')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('hint');
        });
    }
};
