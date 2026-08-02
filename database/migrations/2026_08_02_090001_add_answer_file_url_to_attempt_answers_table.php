<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempt_answers', function (Blueprint $table) {
            // URL audio học sinh nộp cho câu speaking (hoặc upload nói chung).
            $table->string('answer_file_url')->nullable()->after('answer_text');
        });
    }

    public function down(): void
    {
        Schema::table('attempt_answers', function (Blueprint $table) {
            $table->dropColumn('answer_file_url');
        });
    }
};
