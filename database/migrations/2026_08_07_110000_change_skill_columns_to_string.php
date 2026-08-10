<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dùng string thay vì enum để thêm kỹ năng mới không cần ALTER MODIFY (không portable giữa
     * MySQL/SQLite) — cùng cách questions.type đã làm ở 2026_07_31_090001. Sau migration này,
     * thêm giá trị chỉ cần sửa App\Enums\Skill, không đụng schema.
     */
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->string('skill', 20)->default('mixed')->change();
        });

        // Cột này nằm trong unique(['test_attempt_id','skill']) — change() trên SQLite dựng lại
        // bảng, nên phải kiểm tra unique còn sống sau khi migrate (xem SkillColumnTest).
        Schema::table('attempt_skill_scores', function (Blueprint $table) {
            $table->string('skill', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->enum('skill', ['reading', 'listening', 'speaking', 'writing', 'mixed'])
                ->default('mixed')
                ->change();
        });

        Schema::table('attempt_skill_scores', function (Blueprint $table) {
            $table->enum('skill', ['reading', 'listening', 'speaking', 'writing'])->change();
        });
    }
};
