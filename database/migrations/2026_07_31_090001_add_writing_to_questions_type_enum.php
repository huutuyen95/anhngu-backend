<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Dùng string thay vì enum để đổi giá trị hợp lệ không cần ALTER MODIFY (không portable
            // giữa MySQL/SQLite) — cùng cách mission.status đã làm ở 2026_07_30_110000.
            $table->string('type', 30)->default('multiple_choice')->change();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->enum('type', ['multiple_choice', 'fill_blank', 'select', 'upload'])
                ->default('multiple_choice')
                ->change();
        });
    }
};
