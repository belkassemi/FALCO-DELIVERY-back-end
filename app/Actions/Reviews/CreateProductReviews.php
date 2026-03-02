<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Http\Payloads\Reviews\StoreProductReviewPayload;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

final readonly class CreateProductReviews
{
    /**
     * @return Review[]
     */
    public function handle(StoreProductReviewPayload $payload, int $userId): array
    {
        // 1. Verify order belongs to the authenticated customer and is delivered
        $order = Order::query()
            ->where('id', $payload->orderId)
            ->where('customer_id', $userId)
            ->firstOrFail();

        if ($order->status !== 'delivered') {
            throw ValidationException::withMessages([
                'order_id' => 'Reviews can only be submitted for delivered orders.',
            ]);
        }

        // 2. Validate that the order items exist on this specific order
        // and that they haven't already been reviewed by this user.
        $validOrderItemIds = $order->items()->pluck('id')->toArray();
        $createdReviews = [];

        DB::transaction(function () use ($payload, $userId, $validOrderItemIds, &$createdReviews) {
            foreach ($payload->reviews as $reviewItem) {
                if (!in_array($reviewItem->orderItemId, $validOrderItemIds, true)) {
                    throw ValidationException::withMessages([
                        'reviews' => "Order item {$reviewItem->orderItemId} does not belong to order {$payload->orderId}.",
                    ]);
                }

                // PRD §18.1 Guard: No existing review for same order_item_id
                if (Review::where('user_id', $userId)->where('order_item_id', $reviewItem->orderItemId)->exists()) {
                     throw ValidationException::withMessages([
                        'reviews' => "You have already reviewed order item {$reviewItem->orderItemId}.",
                    ]);
                }

                $createdReviews[] = Review::create([
                    'user_id'       => $userId,
                    'order_id'      => $payload->orderId,
                    'order_item_id' => $reviewItem->orderItemId,
                    'type'          => 'product',
                    'rating'        => $reviewItem->rating,
                    'comment'       => $reviewItem->comment,
                    // Note: store_id is null for product-level reviews.
                ]);
            }
        });

        return $createdReviews;
    }
}
