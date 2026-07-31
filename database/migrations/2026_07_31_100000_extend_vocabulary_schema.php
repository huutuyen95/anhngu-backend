<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decks', function (Blueprint $table) {
            $table->foreignId('classroom_id')->nullable()->after('owner_id')->constrained()->nullOnDelete();
            $table->text('description')->nullable()->after('slug');
            $table->string('tts_voice', 40)->default('en-GB-female')->after('description');
            $table->decimal('tts_rate', 3, 2)->default(0.90)->after('tts_voice');
            // '1' | '2' | 'auto' (auto = tự đọc khi lật thẻ) — string để chứa 'auto'.
            $table->string('tts_repeat', 8)->default('1')->after('tts_rate');
            $table->boolean('is_published')->default(false)->after('is_public');
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->string('pos', 12)->nullable()->after('meaning');
        });

        Schema::table('card_progress', function (Blueprint $table) {
            $table->timestamp('reviewed_at')->nullable()->after('review_count');
        });

        // 1 bộ từ gán được nhiều lớp.
        Schema::create('deck_classroom', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deck_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['deck_id', 'classroom_id']);
        });

        // Tra phiên âm tự động (seed từ từ điển mở).
        Schema::create('ipa_dictionary', function (Blueprint $table) {
            $table->id();
            $table->string('word')->unique();
            $table->string('ipa', 120)->nullable();
            $table->string('pos', 12)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipa_dictionary');
        Schema::dropIfExists('deck_classroom');
        Schema::table('card_progress', fn (Blueprint $t) => $t->dropColumn('reviewed_at'));
        Schema::table('cards', fn (Blueprint $t) => $t->dropColumn('pos'));
        Schema::table('decks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('classroom_id');
            $table->dropColumn(['description', 'tts_voice', 'tts_rate', 'tts_repeat', 'is_published']);
        });
    }
};
