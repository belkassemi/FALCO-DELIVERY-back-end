<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PRD §5.1 / §5.7 — Add store_notified, store_confirmed, and no_courier_found
 * to the order status CHECK constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE orders
            DROP CONSTRAINT IF EXISTS orders_status_check
        ");

        DB::statement("
            ALTER TABLE orders
            ADD CONSTRAINT orders_status_check
            CHECK (status IN (
                'pending',
                'store_notified',
                'store_confirmed',
                'courier_searching',
                'courier_assigned',
                'preparing',
                'ready',
                'picked_up',
                'delivered',
                'cancelled',
                'rejected',
                'no_courier_found'
            ))
        ");
    }

    public function down(): void
    {
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
};
