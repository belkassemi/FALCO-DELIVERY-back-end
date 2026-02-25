<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password_reset_token')->nullable()->after('password');
            $table->timestamp('password_reset_expires_at')->nullable()->after('password_reset_token');
            $table->boolean('email_verified')->default(false)->after('email_verified_at');
            $table->string('email_verification_token')->nullable()->after('email_verified');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'password_reset_token',
                'password_reset_expires_at',
                'email_verified',
                'email_verification_token',
            ]);
        });
    }
};
