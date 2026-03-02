<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Http\Payloads\Reports\StoreReportPayload;
use App\Models\Order;
use App\Models\OrderReport;
use Illuminate\Validation\ValidationException;

final readonly class CreateReport
{
    public function handle(StoreReportPayload $payload, int $userId): OrderReport
    {
        // 1. Guard: Order must belong to authenticated customer
        $order = Order::query()
            ->where('id', $payload->orderId)
            ->where('customer_id', $userId)
            ->firstOrFail();

        // 2. Guard: Order status must be active OR delivered within 24h
        $activeStatuses = ['courier_assigned', 'preparing', 'ready', 'picked_up'];
        $isActive = in_array($order->status, $activeStatuses, true);
        
        $isRecentlyDelivered = $order->status === 'delivered' && 
                               $order->updated_at && 
                               $order->updated_at->diffInHours(now()) <= 24;

        if (!$isActive && !$isRecentlyDelivered) {
            throw ValidationException::withMessages([
                'order_id' => 'Reports can only be submitted for active orders or within 24 hours of delivery.',
            ]);
        }

        // 3. Guard: No existing report for the same order by this customer
        $existingReport = OrderReport::query()
            ->where('user_id', $userId)
            ->where('order_id', $order->id)
            ->exists();

        if ($existingReport) {
            throw ValidationException::withMessages([
                'order_id' => 'You have already submitted a report for this order.',
            ]);
        }

        // 4. Create the report
        return OrderReport::create([
            'order_id'    => $order->id,
            'user_id'     => $userId,
            'type'        => $payload->type,
            'description' => $payload->description,
            'status'      => 'open',
        ]);
    }
}
