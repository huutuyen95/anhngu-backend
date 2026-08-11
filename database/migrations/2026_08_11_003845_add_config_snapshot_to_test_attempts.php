<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_attempts', function (Blueprint $table) {
            // Ảnh chụp cấu hình lúc BẮT ĐẦU làm bài (thang điểm, số lần rời…) để đổi cấu hình
            // giữa chừng không làm sai điểm bài đang dở.
            $table->json('config_snapshot')->nullable()->after('tab_exit_count');
        });
    }

    public function down(): void
    {
        Schema::table('test_attempts', function (Blueprint $table) {
            $table->dropColumn('config_snapshot');
        });
    }
};
