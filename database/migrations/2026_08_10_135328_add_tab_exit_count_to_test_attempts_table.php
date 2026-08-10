<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_attempts', function (Blueprint $table) {
            // Số lần học sinh rời khỏi tab làm bài (chống gian lận). Lưu server-side để
            // reload không reset được bộ đếm.
            $table->unsignedSmallInteger('tab_exit_count')->default(0)->after('question_count');
        });
    }

    public function down(): void
    {
        Schema::table('test_attempts', function (Blueprint $table) {
            $table->dropColumn('tab_exit_count');
        });
    }
};
