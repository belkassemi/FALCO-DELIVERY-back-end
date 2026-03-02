<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Http\Payloads\Reviews\StoreStoreReviewPayload;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Validation\ValidationException;

final readonly class CreateOrUpdateStoreReview
{
    public function handle(StoreStoreReviewPayload $payload, int $userId): Review
    {
        // 1. Guard: Customer must have at least one delivered order from this store
        $latestOrder = Order::query()
            ->where('customer_id', $userId)
            ->where('store_id', $payload->storeId) 
            ->where('status', 'delivered')
            ->latest()
            ->first();

        if (!$latestOrder) {
            throw ValidationException::withMessages([
                'store_id' => 'You must have at least one delivered order from this store before leaving a review.',
            ]);
        }

        // 2. Upsert the review (Customer can review once, but update anytime)
        return Review::updateOrCreate(
            [
                'user_id'  => $userId,
                'store_id' => $payload->storeId,
                'type'     => 'store',
            ],
            [
                'order_id' => $latestOrder->id,
                'rating'   => $payload->rating,
                'comment'  => $payload->comment,
            ]
        );
    }
}
