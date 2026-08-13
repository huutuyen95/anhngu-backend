<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('birthday')->nullable()->after('phone');
            $table->string('gender', 20)->nullable()->after('birthday'); // male|female|unspecified
            $table->string('address', 255)->nullable()->after('gender');
            $table->string('facebook_url', 255)->nullable()->after('address');
            $table->timestamp('password_changed_at')->nullable()->after('facebook_url');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['birthday', 'gender', 'address', 'facebook_url', 'password_changed_at']);
        });
    }
};
