<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRD §10.2: Add refund_method and resolved_at columns to the refunds table.
 * These are required for the admin approval flow (cash vs bank_transfer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->enum('refund_method', ['cash', 'bank_transfer'])->nullable()->after('status');
            $table->timestamp('resolved_at')->nullable()->after('processed_at');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropColumn(['refund_method', 'resolved_at']);
        });
    }
};
