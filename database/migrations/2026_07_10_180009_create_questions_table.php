<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_section_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order');
            $table->enum('type', ['multiple_choice', 'fill_blank', 'select', 'upload'])->default('multiple_choice');
            $table->text('content')->nullable();
            $table->string('audio_url')->nullable();
            $table->text('explanation')->nullable();
            $table->decimal('score', 4, 2)->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
