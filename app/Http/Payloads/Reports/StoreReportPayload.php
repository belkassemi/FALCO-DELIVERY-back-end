<?php

declare(strict_types=1);

namespace App\Http\Payloads\Reports;

final readonly class StoreReportPayload
{
    public function __construct(
        public int $orderId,
        public string $type,
        public ?string $description,
    ) {}

    public function toArray(): array
    {
        return [
            'order_id'    => $this->orderId,
            'type'        => $this->type,
            'description' => $this->description,
        ];
    }
}
