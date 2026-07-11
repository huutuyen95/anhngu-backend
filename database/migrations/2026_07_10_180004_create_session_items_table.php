<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order');
            $table->morphs('itemable');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_items');
    }
};
