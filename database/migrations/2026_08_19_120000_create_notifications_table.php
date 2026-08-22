<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // Chống nhắc trùng "sắp đến hạn" mỗi ngày.
        Schema::table('missions', function (Blueprint $table) {
            $table->timestamp('deadline_notified_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('missions', fn (Blueprint $table) => $table->dropColumn('deadline_notified_at'));
        Schema::dropIfExists('notifications');
    }
};
