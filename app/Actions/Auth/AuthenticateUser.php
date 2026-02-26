<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Http\Payloads\Auth\LoginPayload;
use Illuminate\Support\Facades\RateLimiter;

final readonly class AuthenticateUser
{
    /**
     * @return array{token: string, user: \App\Models\User}
     */
    public function handle(LoginPayload $payload, string $ip): array
    {
        $key = 'login:' . $ip;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw new \Exception('Too many login attempts. Try again in ' . ceil($seconds / 60) . ' minute(s).');
        }

        $token = auth('api')->attempt([
            'email'    => $payload->email,
            'password' => $payload->password,
        ]);

        if (!$token) {
            RateLimiter::hit($key, 600);
            throw new \Exception('Invalid credentials');
        }

        RateLimiter::clear($key);

        $user = auth('api')->user();

        if ($user->status !== 'active') {
            auth('api')->logout();
            throw new \Exception('Account suspended or banned.');
        }

        return ['token' => $token, 'user' => $user];
    }
}
