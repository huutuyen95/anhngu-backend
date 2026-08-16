<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deck_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::table('decks', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('owner_id')->constrained('deck_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('decks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
        Schema::dropIfExists('deck_categories');
    }
};
