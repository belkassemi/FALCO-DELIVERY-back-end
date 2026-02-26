<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\V1;

use App\Actions\Auth\RequestOtp;
use App\Http\Requests\Auth\V1\RequestOtpRequest;
use App\Http\Responses\JsonDataResponse;
use App\Http\Responses\JsonErrorResponse;
use Illuminate\Http\JsonResponse;

final readonly class RequestOtpController
{
    public function __construct(
        private RequestOtp $requestOtp,
    ) {}

    public function __invoke(RequestOtpRequest $request): JsonResponse
    {
        try {
            $this->requestOtp->handle(
                payload: $request->payload(),
            );

            return (new JsonDataResponse(
                data: ['message' => 'OTP sent successfully. Valid for 3 minutes.'],
            ))->toResponse($request);
        } catch (\Exception $e) {
            return (new JsonErrorResponse(
                title: 'Rate Limited',
                detail: $e->getMessage(),
                status: 429,
            ))->toResponse($request);
        }
    }
}
