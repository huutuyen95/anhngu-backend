<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_attempts', function (Blueprint $table) {
            // Dùng string thay vì enum để đổi giá trị hợp lệ không cần ALTER MODIFY (không portable
            // giữa MySQL/SQLite) — cùng cách mission.status đã làm ở 2026_07_30_110000.
            $table->string('status', 20)->default('in_progress')->change();
        });
    }

    public function down(): void
    {
        Schema::table('test_attempts', function (Blueprint $table) {
            $table->enum('status', ['in_progress', 'submitted', 'expired'])
                ->default('in_progress')
                ->change();
        });
    }
};
