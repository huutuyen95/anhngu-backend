<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_part_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order');
            $table->text('instruction')->nullable();
            $table->longText('passage')->nullable();
            $table->string('audio_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_sections');
    }
};
