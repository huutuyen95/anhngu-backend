<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->enum('skill', ['reading', 'listening', 'speaking', 'writing', 'mixed'])->default('mixed');
            $table->boolean('is_combo')->default(false);
            $table->string('thumbnail_url')->nullable();
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->decimal('total_score', 4, 2)->default(10);
            $table->string('scoring_method')->default('by_correct_count');
            $table->boolean('ai_grading')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tests');
    }
};
