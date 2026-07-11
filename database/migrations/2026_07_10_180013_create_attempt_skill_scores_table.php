<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_skill_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_attempt_id')->constrained()->cascadeOnDelete();
            $table->enum('skill', ['reading', 'listening', 'speaking', 'writing']);
            $table->decimal('score', 4, 2);
            $table->timestamps();

            $table->unique(['test_attempt_id', 'skill']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_skill_scores');
    }
};
