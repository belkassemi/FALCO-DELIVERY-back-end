<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRD §19 — Order Issue Reporting System
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            $table->enum('type', [
                'late_delivery',
                'courier_no_show',
                'missing_items',
                'wrong_items',
                'courier_behavior',
                'store_issue',
                'damaged_product',
                'other'
            ]);
            
            $table->text('description')->nullable();
            
            $table->enum('status', ['open', 'resolved'])->default('open');
            $table->text('admin_response')->nullable();
            $table->string('action_taken')->nullable();
            
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // PRD §19.5 Unique constraint: one report per order per customer.
            $table->unique(['user_id', 'order_id'], 'user_order_report_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_reports');
    }
};
