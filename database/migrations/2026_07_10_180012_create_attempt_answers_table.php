<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_option_id')->nullable()->constrained()->nullOnDelete();
            $table->text('answer_text')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('score', 4, 2)->default(0);
            $table->timestamps();

            $table->unique(['test_attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_answers');
    }
};
