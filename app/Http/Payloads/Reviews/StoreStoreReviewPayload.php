<?php

declare(strict_types=1);

namespace App\Http\Payloads\Reviews;

final readonly class StoreStoreReviewPayload
{
    public function __construct(
        public int $storeId,
        public int $rating,
        public ?string $comment,
    ) {}
}
