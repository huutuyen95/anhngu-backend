<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Thư mục gắn theo lớp (mỗi lớp có cây riêng); null = dùng chung / chưa gắn lớp.
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            // Cây 2 cấp: parent_id null = gốc.
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('test_categories')->nullOnDelete();
            $table->index(['classroom_id', 'parent_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_categories');
    }
};
