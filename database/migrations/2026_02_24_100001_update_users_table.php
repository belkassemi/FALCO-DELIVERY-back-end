<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('avatar')->nullable()->after('phone');
            $table->enum('role', ['customer', 'courier', 'restaurant_owner', 'admin'])->default('customer')->after('avatar');
            $table->boolean('is_blocked')->default(false)->after('role');
            $table->string('phone_verified_at')->nullable()->after('is_blocked');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'avatar', 'role', 'is_blocked', 'phone_verified_at']);
        });
    }
};
