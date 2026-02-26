<?php

namespace App\Services;

use App\Models\PhoneOtp;
use App\Models\SmsLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class OtpService
{
    /**
     * Generate, hash, and store a new OTP for the given phone number.
     * Returns the plain OTP (to be sent via SMS).
     */
    public function generate(string $phone): string
    {
        // Rate limit: max 3 OTP requests per phone per 10 minutes
        $key = 'otp_request:' . $phone;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw new \Exception('Too many OTP requests. Please wait before trying again.');
        }
        RateLimiter::hit($key, 600);

        // Invalidate any previous unexpired OTPs for this phone
        PhoneOtp::where('phone_number', $phone)
            ->whereNull('verified_at')
            ->delete();

        // Generate 6-digit OTP
        $otp = (string) random_int(100000, 999999);

        // Store hashed OTP
        PhoneOtp::create([
            'phone_number' => $phone,
            'otp_hash'     => Hash::make($otp),
            'expires_at'   => now()->addMinutes(3),
            'attempts'     => 0,
        ]);

        return $otp;
    }

    /**
     * Verify an OTP against the stored hash.
     * Returns true if valid, throws exception on failure.
     */
    public function verify(string $phone, string $otp): bool
    {
        $record = PhoneOtp::where('phone_number', $phone)
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (!$record) {
            throw new \Exception('No OTP found for this phone number.');
        }

        if ($record->isExpired()) {
            throw new \Exception('OTP has expired. Please request a new one.');
        }

        if ($record->isMaxAttempts()) {
            throw new \Exception('Maximum verification attempts reached. Please request a new OTP.');
        }

        // Increment attempts
        $record->increment('attempts');

        if (!Hash::check($otp, $record->otp_hash)) {
            throw new \Exception('Invalid OTP. ' . (3 - $record->attempts) . ' attempt(s) remaining.');
        }

        // Mark as verified
        $record->update(['verified_at' => now()]);

        return true;
    }
}
