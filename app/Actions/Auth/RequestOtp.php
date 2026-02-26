<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Http\Payloads\Auth\RequestOtpPayload;
use App\Services\OtpService;
use App\Services\SmsService;

final readonly class RequestOtp
{
    public function __construct(
        private OtpService $otpService,
        private SmsService $smsService,
    ) {}

    public function handle(RequestOtpPayload $payload): void
    {
        $otp = $this->otpService->generate($payload->phoneNumber);
        $this->smsService->sendOtp($payload->phoneNumber, $otp);
    }
}
