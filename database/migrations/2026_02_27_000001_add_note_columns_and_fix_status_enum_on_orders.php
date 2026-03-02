<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PRD §5.5 — Update order status ENUM to the full lifecycle sequence.
 * PRD §5.6 — Add customer_note, store_note, courier_note columns.
 * PRD §10.2 — Add store_id FK alias (orders.store_id instead of restaurant_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // PRD §5.6: Three-level notes system
            $table->text('customer_note')->nullable()->after('notes');
            $table->text('store_note')->nullable()->after('customer_note');
            $table->text('courier_note')->nullable()->after('store_note');

            // PRD §5.5: store_id alias for restaurant_id if not already present
            if (!Schema::hasColumn('orders', 'store_id')) {
                $table->unsignedBigInteger('store_id')->nullable()->after('customer_id');
                $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();
            }
        });

        // PRD §5.5: Update status column to full PRD-compliant ENUM.
        // PostgreSQL does not support modifying ENUM inline with Blueprint::change().
        // We add the new values directly to the existing enum type.
        DB::statement("
            ALTER TABLE orders
            DROP CONSTRAINT IF EXISTS orders_status_check
        ");

        DB::statement("
            ALTER TABLE orders
            ADD CONSTRAINT orders_status_check
            CHECK (status IN (
                'pending',
                'courier_searching',
                'courier_assigned',
                'preparing',
                'ready',
                'picked_up',
                'delivered',
                'cancelled',
                'rejected'
            ))
        ");
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['customer_note', 'store_note', 'courier_note']);
        });

        DB::statement("
            ALTER TABLE orders
            DROP CONSTRAINT IF EXISTS orders_status_check
        ");

        DB::statement("
            ALTER TABLE orders
            ADD CONSTRAINT orders_status_check
            CHECK (status IN ('pending', 'assigned', 'preparing', 'on_the_way', 'delivered', 'cancelled', 'rejected'))
        ");
    }
};
