<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Mảng URL ảnh gợi ý (cô upload nhiều ảnh) — dùng cho câu speaking (mô tả tranh...).
            $table->json('images')->nullable()->after('audio_url');
            // Giới hạn thời lượng ghi âm (giây); null = không giới hạn.
            $table->unsignedSmallInteger('record_limit_seconds')->nullable()->after('images');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['images', 'record_limit_seconds']);
        });
    }
};
