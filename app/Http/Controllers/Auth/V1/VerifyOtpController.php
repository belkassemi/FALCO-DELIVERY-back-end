<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\V1;

use App\Actions\Auth\VerifyOtp;
use App\Http\Requests\Auth\V1\VerifyOtpRequest;
use App\Http\Responses\JsonDataResponse;
use App\Http\Responses\JsonErrorResponse;
use Illuminate\Http\JsonResponse;

final readonly class VerifyOtpController
{
    public function __construct(
        private VerifyOtp $verifyOtp,
    ) {}

    public function __invoke(VerifyOtpRequest $request): JsonResponse
    {
        try {
            $result = $this->verifyOtp->handle(
                payload: $request->payload(),
                ipAddress: $request->ip(),
            );

            return (new JsonDataResponse(
                data: [
                    'access_token' => $result['token'],
                    'token_type'   => 'bearer',
                    'expires_in'   => auth('api')->factory()->getTTL() * 60,
                    'user'         => $result['user'],
                    'is_new_user'  => $result['is_new_user'],
                ],
            ))->toResponse($request);
        } catch (\Exception $e) {
            return (new JsonErrorResponse(
                title: 'Verification Failed',
                detail: $e->getMessage(),
                status: 400,
            ))->toResponse($request);
        }
    }
}
