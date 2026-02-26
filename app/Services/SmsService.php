<?php

namespace App\Services;

use App\Models\SmsLog;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Send an SMS message and log it to sms_logs.
     * In production, integrate with a Moroccan SMS provider (Twilio, Vonage, local gateway).
     */
    public function send(string $phone, string $message, string $type = 'otp'): bool
    {
        try {
            // TODO: Replace with actual SMS provider API call
            // Example: $response = Http::post('https://sms-provider.ma/api/send', [...]);
            $providerResponse = 'simulated_success'; // Placeholder

            Log::info("SMS sent to {$phone}: {$message}");

            // Log SMS for legal compliance (Morocco ANRT requirements)
            SmsLog::create([
                'phone_number'      => $phone,
                'message_type'      => $type,
                'provider_response' => $providerResponse,
                'status'            => 'sent',
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("SMS failed to {$phone}: " . $e->getMessage());

            SmsLog::create([
                'phone_number'      => $phone,
                'message_type'      => $type,
                'provider_response' => $e->getMessage(),
                'status'            => 'failed',
            ]);

            return false;
        }
    }

    /**
     * Send OTP via SMS.
     */
    public function sendOtp(string $phone, string $otp): bool
    {
        $message = "Falco Delivery: Your verification code is {$otp}. Valid for 3 minutes.";
        return $this->send($phone, $message, 'otp');
    }

    /**
     * Send order status update via SMS.
     */
    public function sendOrderUpdate(string $phone, string $message): bool
    {
        return $this->send($phone, $message, 'order_update');
    }
}
