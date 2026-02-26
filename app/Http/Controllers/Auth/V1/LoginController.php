<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\V1;

use App\Actions\Auth\AuthenticateUser;
use App\Http\Requests\Auth\V1\LoginRequest;
use App\Http\Responses\JsonDataResponse;
use App\Http\Responses\JsonErrorResponse;
use Illuminate\Http\JsonResponse;

final readonly class LoginController
{
    public function __construct(
        private AuthenticateUser $authenticateUser,
    ) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authenticateUser->handle(
                payload: $request->payload(),
                ip: $request->ip(),
            );

            return (new JsonDataResponse(
                data: [
                    'access_token' => $result['token'],
                    'token_type'   => 'bearer',
                    'expires_in'   => auth('api')->factory()->getTTL() * 60,
                    'user'         => $result['user'],
                ],
            ))->toResponse($request);
        } catch (\Exception $e) {
            $status = str_contains($e->getMessage(), 'suspended') ? 403 : 401;

            return (new JsonErrorResponse(
                title: 'Authentication Failed',
                detail: $e->getMessage(),
                status: $status,
            ))->toResponse($request);
        }
    }
}
