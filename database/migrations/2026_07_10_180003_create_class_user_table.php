<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['studying', 'finished', 'paused'])->default('studying');
            $table->timestamps();

            $table->unique(['classroom_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_user');
    }
};
