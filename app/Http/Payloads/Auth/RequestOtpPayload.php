<?php

declare(strict_types=1);

namespace App\Http\Payloads\Auth;

final readonly class RequestOtpPayload
{
    public function __construct(
        public string $phoneNumber,
    ) {}
}
