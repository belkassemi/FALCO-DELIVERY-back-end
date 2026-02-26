<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Users: make email & password nullable for OTP-only customers ---
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });

        // --- Orders: add age_confirmation, delivery_distance_km, rename restaurant_id → store_id ---
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('age_confirmation')->default(false)->after('notes');
            $table->timestamp('age_confirmation_at')->nullable()->after('age_confirmation');
            $table->decimal('delivery_distance_km', 8, 2)->nullable()->after('delivery_fee');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['age_confirmation', 'age_confirmation_at', 'delivery_distance_km']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
