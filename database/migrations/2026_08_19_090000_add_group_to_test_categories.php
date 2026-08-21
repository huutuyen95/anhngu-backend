<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thư mục đề chuyển từ trục "theo lớp" (classroom_id) sang trục "nhóm nội dung":
     * group = 'exam' (Đề thi) | 'exercise' (Bài tập). Category giờ là kho toàn cục
     * (classroom_id để null), việc gán cho lớp là hành động Giao bài riêng.
     */
    public function up(): void
    {
        Schema::table('test_categories', function (Blueprint $table) {
            $table->string('group', 20)->default('exam')->after('name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('test_categories', function (Blueprint $table) {
            $table->dropIndex(['group']);
            $table->dropColumn('group');
        });
    }
};
