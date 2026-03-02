<?php

declare(strict_types=1);

namespace App\Http\Payloads\Reviews;

final readonly class ProductReviewItemPayload
{
    public function __construct(
        public int $orderItemId,
        public int $rating,
        public ?string $comment,
    ) {}

    public function toArray(): array
    {
        return [
            'order_item_id' => $this->orderItemId,
            'rating'        => $this->rating,
            'comment'       => $this->comment,
        ];
    }
}
