<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->foreignId('classroom_id')->nullable()->after('assigned_by')->constrained()->nullOnDelete();
            $table->foreignId('class_session_id')->nullable()->after('classroom_id')->constrained('class_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('classroom_id');
            $table->dropConstrainedForeignId('class_session_id');
        });
    }
};
