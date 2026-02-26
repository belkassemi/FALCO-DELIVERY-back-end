<?php

declare(strict_types=1);

namespace App\Http\Payloads\Auth;

final readonly class VerifyOtpPayload
{
    public function __construct(
        public string $phoneNumber,
        public string $otp,
        public ?string $fullName,
        public bool $tosAccepted,
    ) {}
}
