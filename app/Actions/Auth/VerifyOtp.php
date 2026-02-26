<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Http\Payloads\Auth\VerifyOtpPayload;
use App\Models\TosAcceptance;
use App\Models\User;
use App\Models\Wallet;
use App\Services\OtpService;

final readonly class VerifyOtp
{
    public function __construct(
        private OtpService $otpService,
    ) {}

    /**
     * @return array{user: User, token: string, is_new_user: bool}
     */
    public function handle(VerifyOtpPayload $payload, string $ipAddress): array
    {
        $this->otpService->verify($payload->phoneNumber, $payload->otp);

        $user = User::where('phone', $payload->phoneNumber)->first();
        $isNewUser = false;

        if (!$user) {
            $isNewUser = true;
            $user = User::create([
                'name'              => $payload->fullName ?? 'Customer',
                'phone'             => $payload->phoneNumber,
                'role'              => 'customer',
                'status'            => 'active',
                'phone_verified_at' => now(),
            ]);

            Wallet::create(['user_id' => $user->id, 'balance' => 0]);
        } elseif (!$user->phone_verified_at) {
            $user->update(['phone_verified_at' => now()]);
        }

        TosAcceptance::create([
            'user_id'     => $user->id,
            'tos_version' => '1.0',
            'ip_address'  => $ipAddress,
            'accepted_at' => now(),
        ]);

        if ($user->status !== 'active') {
            throw new \Exception('Account suspended or banned.');
        }

        $token = auth('api')->login($user);

        return [
            'user'        => $user,
            'token'       => $token,
            'is_new_user' => $isNewUser,
        ];
    }
}
