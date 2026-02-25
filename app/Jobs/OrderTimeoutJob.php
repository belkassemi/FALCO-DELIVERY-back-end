<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * OrderTimeoutJob
 *
 * This job enforces system-level order lifecycle timeout rules.
 * It should be dispatched via the Laravel Scheduler (app/Console/Kernel.php):
 *
 *   $schedule->job(new OrderTimeoutJob)->everyFiveMinutes();
 *
 * ─────────────────────────────────────────────────────────────────────
 * TIMEOUT RULES:
 *
 * 1. RESTAURANT ACCEPTANCE TIMEOUT (15 minutes)
 *    If a restaurant does not move the order from 'pending' to 'preparing',
 *    the order is auto-cancelled and the customer is notified.
 *
 * 2. COURIER PICKUP TIMEOUT (30 minutes)
 *    If the courier does not mark the order as 'on_the_way' (pickup)
 *    within 30 min of being assigned, the order is unassigned and re-dispatched.
 *
 * 3. PAYMENT EXPIRY TIMEOUT (10 minutes)
 *    Pending payment records older than 10 minutes are marked as 'failed'.
 * ─────────────────────────────────────────────────────────────────────
 */
class OrderTimeoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // ── Rule 1: Auto-cancel if restaurant hasn't accepted within 15 min ──
        $pendingTooLong = Order::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(15))
            ->get();

        foreach ($pendingTooLong as $order) {
            $order->update(['status' => 'cancelled']);
            Log::info("OrderTimeoutJob: Order #{$order->id} auto-cancelled (no restaurant acceptance within 15 min).");
            // TODO: notify customer via push (device_token / AppNotification)
        }

        // ── Rule 2: Auto-release courier if no pickup within 30 min ──
        $assignedTooLong = Order::where('status', 'assigned')
            ->where('updated_at', '<', now()->subMinutes(30))
            ->get();

        foreach ($assignedTooLong as $order) {
            $order->update(['status' => 'pending', 'courier_id' => null]);
            Log::info("OrderTimeoutJob: Order #{$order->id} courier released (no pickup within 30 min).");
            // TODO: re-dispatch via DispatchService
        }

        // ── Rule 3: Expire unpaid payments after 10 minutes ──
        Payment::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(10))
            ->update(['status' => 'failed']);

        Log::info('OrderTimeoutJob: Expired unpaid payments cleared.');
    }
}
