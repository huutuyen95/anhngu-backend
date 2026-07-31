<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            // false = học sinh KHÔNG thấy tiến trình này ở lộ trình lớp (bài đã giao vẫn giữ).
            $table->boolean('is_visible')->default(true)->after('held_on');
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropColumn('is_visible');
        });
    }
};
