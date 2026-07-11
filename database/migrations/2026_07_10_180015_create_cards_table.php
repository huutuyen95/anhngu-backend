<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deck_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order');
            $table->string('term');
            $table->string('meaning');
            $table->string('ipa')->nullable();
            $table->string('audio_url')->nullable();
            $table->string('image_url')->nullable();
            $table->text('example')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
