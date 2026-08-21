<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dạng đề (hard-coded 2 giá trị): 'standard' (Đề tiêu chuẩn) | 'ielts_simulation' (IELTS Simulation).
     * Trục độc lập với thư mục (category) — thư mục dùng chung cho cả 2 dạng.
     */
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->string('format', 20)->default('standard')->after('skill')->index();
        });
    }

    public function down(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->dropIndex(['format']);
            $table->dropColumn('format');
        });
    }
};
