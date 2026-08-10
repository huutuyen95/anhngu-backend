<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            // Mô tả ngắn hiện trên card ở "Thư viện → Đề thi" (giới hạn 500 ký tự ở form request).
            $table->text('description')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
