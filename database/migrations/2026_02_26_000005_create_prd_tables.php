<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Settings table (admin-configurable values) ---
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->timestamps();
        });

        // --- Phone OTPs ---
        Schema::create('phone_otps', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number', 20)->index();
            $table->string('otp_hash');
            $table->timestamp('expires_at');
            $table->tinyInteger('attempts')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        // --- ToS Acceptances ---
        Schema::create('tos_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('tos_version')->default('1.0');
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('accepted_at');
            $table->timestamps();
        });

        // --- SMS Logs ---
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number', 20)->index();
            $table->string('message_type'); // otp, order_update, marketing
            $table->text('provider_response')->nullable();
            $table->string('status')->default('sent'); // sent, failed, delivered
            $table->timestamps();
        });

        // --- Courier Earnings ---
        Schema::create('courier_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->string('type')->default('delivery'); // delivery, bonus, tip
            $table->timestamps();
        });

        // --- Courier Monthly Stats ---
        Schema::create('courier_monthly_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_id')->constrained('users')->onDelete('cascade');
            $table->string('month', 7); // YYYY-MM
            $table->integer('total_deliveries')->default(0);
            $table->decimal('total_distance_km', 10, 2)->default(0);
            $table->decimal('avg_delivery_time_min', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['courier_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_monthly_stats');
        Schema::dropIfExists('courier_earnings');
        Schema::dropIfExists('sms_logs');
        Schema::dropIfExists('tos_acceptances');
        Schema::dropIfExists('phone_otps');
        Schema::dropIfExists('settings');
    }
};
