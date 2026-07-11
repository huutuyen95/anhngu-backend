<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('attempt_category', ['test', 'exercise', 'speaking'])->default('test');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('total_score', 5, 2)->nullable();
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('question_count')->default(0);
            $table->enum('status', ['in_progress', 'submitted', 'expired'])->default('in_progress');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_attempts');
    }
};
