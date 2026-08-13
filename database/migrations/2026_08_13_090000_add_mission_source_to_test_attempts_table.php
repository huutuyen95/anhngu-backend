<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tách lượt làm bài "được giao trong lớp" khỏi lượt "tự luyện ở thư viện".
     *
     * `mission_id` là danh tính của bài được giao (mission = 1 nội dung giao cho 1 học viên
     * trong 1 buổi). `null` = tự luyện. `source` chỉ là dạng đọc nhanh của cùng thông tin đó,
     * giữ riêng để filter/hiển thị cột "Nguồn" ở khu giáo viên mà không phải join missions.
     */
    public function up(): void
    {
        Schema::table('test_attempts', function (Blueprint $table) {
            $table->foreignId('mission_id')->nullable()->after('classroom_id')
                ->constrained()->nullOnDelete();
            $table->string('source', 20)->default('library')->after('mission_id');

            // Truy vấn nóng nhất: lượt của em trên một đề, trong một scope (mission hoặc thư viện).
            $table->index(['user_id', 'test_id', 'mission_id'], 'test_attempts_scope_index');
        });

        // Dữ liệu cũ: classroom_id chưa bao giờ được ghi nên không suy ngược được lượt nào là
        // bài giao — coi tất cả là tự luyện (đúng với default của cột).
        DB::table('test_attempts')->update(['source' => 'library']);
    }

    public function down(): void
    {
        Schema::table('test_attempts', function (Blueprint $table) {
            $table->dropIndex('test_attempts_scope_index');
            $table->dropConstrainedForeignId('mission_id');
            $table->dropColumn('source');
        });
    }
};
