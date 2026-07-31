<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            // Mở rộng status: thêm 'draft' (bản nháp) và 'scheduled' (lên lịch) ngoài todo/done.
            $table->string('status', 20)->default('todo')->change();
            $table->unsignedInteger('attempts_allowed')->default(1)->after('due_date');
            $table->timestamp('scheduled_at')->nullable()->after('attempts_allowed');
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropColumn(['attempts_allowed', 'scheduled_at']);
            $table->enum('status', ['todo', 'done'])->default('todo')->change();
        });
    }
};
