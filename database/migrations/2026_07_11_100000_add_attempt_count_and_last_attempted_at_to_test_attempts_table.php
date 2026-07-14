<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_attempts', function (Blueprint $table) {
            $table->unsignedInteger('attempt_count')->default(1)->after('question_count');
            $table->timestamp('last_attempted_at')->nullable()->after('attempt_count');
        });
    }

    public function down(): void
    {
        Schema::table('test_attempts', function (Blueprint $table) {
            $table->dropColumn(['attempt_count', 'last_attempted_at']);
        });
    }
};
