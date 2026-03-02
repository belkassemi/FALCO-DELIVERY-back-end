<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\NotificationService;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * OrderTimeoutJob — PRD-compliant order lifecycle enforcer.
 *
 * Dispatched via: $schedule->job(new OrderTimeoutJob)->everyFiveMinutes();
 *
 * TIMEOUT RULES:
 *
 * 1. STORE ACCEPTANCE TIMEOUT (configurable, default 10 minutes)
 *    If a store does not accept/reject a store_notified order,
 *    the order is auto-cancelled and the customer is notified.
 *
 * 2. COURIER PICKUP TIMEOUT (30 minutes)
 *    If the courier does not pick up within 30 min of being assigned,
 *    the order is unassigned and re-dispatched.
 *
 * 3. NO COURIER FOUND TIMEOUT (15 minutes)
 *    Orders stuck in courier_searching for too long are marked no_courier_found.
 *
 * 4. PAYMENT EXPIRY TIMEOUT (10 minutes)
 *    Pending payment records older than 10 minutes are marked as 'failed'.
 */
class OrderTimeoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        NotificationService $notificationService,
        SmsService $smsService,
    ): void
    {
        // Configurable timeout via admin settings endpoint (default: 10 minutes)
        $storeTimeout = (int) Setting::getValue('store_acceptance_timeout', 10);

        // ── Rule 1: Auto-cancel if store hasn't accepted within configured timeout ──
        $storeNotifiedTooLong = Order::where('status', Order::STATUS_STORE_NOTIFIED)
            ->where('created_at', '<', now()->subMinutes($storeTimeout))
            ->with('customer')
            ->get();

        foreach ($storeNotifiedTooLong as $order) {
            $order->update(['status' => Order::STATUS_CANCELLED]);
            Log::info("OrderTimeoutJob: Order #{$order->id} auto-cancelled (store did not respond within {$storeTimeout} min).");

            if ($order->customer) {
                $notificationService->sendToCustomer(
                    $order->customer,
                    'Order Cancelled',
                    'Your order was automatically cancelled because the store did not respond in time.',
                    ['order_id' => $order->id, 'type' => 'order_timeout']
                );
            }
        }

        // ── Rule 2: Auto-release courier if no pickup within 30 min ──
        $courierAssignedTooLong = Order::where('status', Order::STATUS_COURIER_ASSIGNED)
            ->where('updated_at', '<', now()->subMinutes(30))
            ->get();

        foreach ($courierAssignedTooLong as $order) {
            $order->update([
                'status'     => Order::STATUS_COURIER_SEARCHING,
                'courier_id' => null,
            ]);
            Log::info("OrderTimeoutJob: Order #{$order->id} courier released (no pickup within 30 min). Re-dispatching.");
            // Will be picked up by DispatchService on next cycle or manually
        }

        // ── Rule 3: No-courier-found timeout (15 min in courier_searching) ──
        $noCourierTooLong = Order::where('status', Order::STATUS_COURIER_SEARCHING)
            ->where('updated_at', '<', now()->subMinutes(15))
            ->with('customer')
            ->get();

        foreach ($noCourierTooLong as $order) {
            $order->update(['status' => Order::STATUS_NO_COURIER_FOUND]);
            Log::info("OrderTimeoutJob: Order #{$order->id} marked no_courier_found (15 min in courier_searching).");

            if ($order->customer) {
                $notificationService->sendToCustomer(
                    $order->customer,
                    'No Courier Available',
                    'We could not find a courier for your order. Please contact our WhatsApp support for assistance.',
                    ['order_id' => $order->id, 'type' => 'no_courier_found']
                );

                $smsService->sendOrderUpdate(
                    $order->customer->phone,
                    "[FALCO DELIVERY] لم نتمكن من إيجاد سائق لطلبك. يرجى التواصل مع فريق الدعم عبر واتساب للمساعدة."
                );
            }
        }

        // ── Rule 4: Expire unpaid payments after 10 minutes ──
        Payment::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(10))
            ->update(['status' => 'failed']);

        Log::info('OrderTimeoutJob: Timeout rules applied successfully.');
    }
}
