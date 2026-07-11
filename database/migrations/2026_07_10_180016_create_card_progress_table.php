<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['new', 'learning', 'known'])->default('new');
            $table->timestamp('next_review_at')->nullable();
            $table->float('ease')->default(2.5);
            $table->unsignedInteger('review_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'card_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_progress');
    }
};
