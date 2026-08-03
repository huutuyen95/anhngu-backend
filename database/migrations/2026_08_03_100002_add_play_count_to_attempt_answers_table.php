<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempt_answers', function (Blueprint $table) {
            // Số lần học sinh đã bấm nghe cho câu này — đếm & chặn ở server (max_plays).
            $table->unsignedTinyInteger('play_count')->default(0)->after('answer_file_url');
        });
    }

    public function down(): void
    {
        Schema::table('attempt_answers', function (Blueprint $table) {
            $table->dropColumn('play_count');
        });
    }
};
