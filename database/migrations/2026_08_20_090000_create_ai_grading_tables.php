<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chấm bài bằng AI — hai bảng, cả hai đều CHỈ ĐỂ THAM KHẢO, không phải điểm chính thức.
     *
     * `attempt_ai_suggestions`: đề xuất của AI cho từng câu. Điểm thật vẫn nằm ở
     * `attempt_answers.score` và chỉ được ghi khi cô bấm Lưu ở màn chấm — AI không bao giờ
     * tự ghi đè, và học viên không thấy gì cho tới khi cô duyệt.
     *
     * `ai_usage_logs`: mỗi lần gọi API ghi một dòng để cộng dồn chi phí trong tháng, đối
     * chiếu với hạn mức cô đặt ở Cài đặt. Hết hạn mức thì ngừng gọi và báo cô.
     */
    public function up(): void
    {
        Schema::create('attempt_ai_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();

            $table->decimal('score', 6, 2)->nullable();
            // Nhận xét đã ghép sẵn thành văn bản ("Điểm đề xuất: …" + từng tiêu chí) để cô
            // dán thẳng vào ô nhận xét, không phải tự diễn giải JSON.
            $table->text('feedback')->nullable();
            // Điểm từng tiêu chí (nội dung / từ vựng / ngữ pháp / trôi chảy) — giữ dạng json
            // để đổi bộ tiêu chí sau này không phải migrate.
            $table->json('criteria')->nullable();

            $table->string('provider', 30);
            $table->string('model', 60);
            // Nguyên văn phản hồi — để dò khi AI trả sai định dạng.
            $table->longText('raw_response')->nullable();
            $table->string('status', 20)->default('ok'); // ok | failed
            $table->text('error')->nullable();

            $table->timestamps();

            $table->unique(['test_attempt_id', 'question_id']);
        });

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_attempt_id')->nullable()->constrained()->nullOnDelete();

            $table->string('provider', 30);
            $table->string('model', 60);
            $table->string('kind', 20); // text | audio

            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            // Chi phí ước tính theo bảng giá lưu ở config — chỉ để cô theo dõi, hoá đơn thật
            // vẫn là của OpenAI.
            $table->decimal('cost_usd', 10, 6)->default(0);

            $table->timestamps();

            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('attempt_ai_suggestions');
    }
};
