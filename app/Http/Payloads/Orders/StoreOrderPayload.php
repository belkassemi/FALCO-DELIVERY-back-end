<?php

declare(strict_types=1);

namespace App\Http\Payloads\Orders;

final readonly class StoreOrderPayload
{
    /**
     * @param array<int, array{id: int, qty: int}> $items
     */
    public function __construct(
        public int $storeId,
        public int $addressId,
        public array $items,
        public bool $ageConfirmation,
    ) {}
}
