<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['document', 'lecture'])->default('document');
            $table->string('title');
            $table->string('slug')->unique();
            $table->foreignId('category_id')->nullable()->constrained('document_categories')->nullOnDelete();
            $table->string('thumbnail_url')->nullable();
            $table->longText('body')->nullable();
            $table->string('excerpt')->nullable();
            $table->unsignedInteger('reading_minutes')->default(1);
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('view_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('document_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('mime')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('document_classroom', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->unique(['document_id', 'classroom_id']);
        });

        Schema::create('document_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('progress_pct')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['document_id', 'user_id']);
        });

        // Sổ tay từ vựng của học sinh (FE 11 — lưu từ khi bôi đen tra từ điển).
        Schema::create('user_vocab', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('word');
            $table->string('meaning')->nullable();
            $table->string('ipa', 120)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'word']);
        });

        // Bổ sung nghĩa tiếng Việt cho từ điển tra cứu.
        Schema::table('ipa_dictionary', function (Blueprint $table) {
            $table->string('meaning_vi')->nullable()->after('pos');
        });
    }

    public function down(): void
    {
        Schema::table('ipa_dictionary', fn (Blueprint $t) => $t->dropColumn('meaning_vi'));
        Schema::dropIfExists('user_vocab');
        Schema::dropIfExists('document_views');
        Schema::dropIfExists('document_classroom');
        Schema::dropIfExists('document_attachments');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_categories');
    }
};
