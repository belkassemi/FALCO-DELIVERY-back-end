<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Models\Order;

final readonly class ReorderFromPast
{
    /**
     * @return array{store_id: int, items: array, unavailable: array, message: string}
     */
    public function handle(int $orderId, int $customerId): array
    {
        $pastOrder = Order::with('items.product')
            ->where('customer_id', $customerId)
            ->findOrFail($orderId);

        $unavailable = [];
        $available = [];

        foreach ($pastOrder->items as $item) {
            if (!$item->product || !$item->product->is_available || $item->product->trashed()) {
                $unavailable[] = [
                    'product_id' => $item->product_id,
                    'name'       => $item->product?->name ?? 'Removed product',
                ];
            } else {
                $available[] = [
                    'id'  => $item->product_id,
                    'qty' => $item->quantity,
                ];
            }
        }

        return [
            'store_id'    => $pastOrder->store_id,
            'items'       => $available,
            'unavailable' => $unavailable,
            'message'     => count($unavailable) > 0
                ? 'Some items from your previous order are no longer available.'
                : 'All items available. Ready to reorder.',
        ];
    }
}
