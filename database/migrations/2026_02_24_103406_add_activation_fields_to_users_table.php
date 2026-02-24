<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('activation_code')->nullable()->unique();
            $table->timestamp('activation_expires_at')->nullable();
            $table->integer('activation_attempts')->default(0);
            $table->boolean('is_activated')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['activation_code', 'activation_expires_at', 'activation_attempts', 'is_activated']);
        });
    }
};
