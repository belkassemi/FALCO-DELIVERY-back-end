<?php

declare(strict_types=1);

namespace App\Http\Payloads\Reviews;

final readonly class StoreProductReviewPayload
{
    /**
     * @param int $orderId
     * @param ProductReviewItemPayload[] $reviews
     */
    public function __construct(
        public int $orderId,
        public array $reviews,
    ) {}
}
