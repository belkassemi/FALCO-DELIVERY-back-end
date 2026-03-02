<?php

declare(strict_types=1);

namespace App\Http\Payloads\Reports;

final readonly class ResolveReportPayload
{
    public function __construct(
        public string $adminResponse,
        public string $actionTaken,
    ) {}

    public function toArray(): array
    {
        return [
            'admin_response' => $this->adminResponse,
            'action_taken'   => $this->actionTaken,
        ];
    }
}
