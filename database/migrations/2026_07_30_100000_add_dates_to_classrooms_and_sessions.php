<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->date('starts_on')->nullable()->after('description');
            $table->date('ends_on')->nullable()->after('starts_on');
        });

        Schema::table('class_sessions', function (Blueprint $table) {
            $table->text('note')->nullable()->after('title');
            $table->date('held_on')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn(['starts_on', 'ends_on']);
        });
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropColumn(['note', 'held_on']);
        });
    }
};
