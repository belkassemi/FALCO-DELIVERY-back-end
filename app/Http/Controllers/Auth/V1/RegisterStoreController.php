<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\V1;

use App\Actions\Auth\RegisterStore;
use App\Http\Requests\Auth\V1\RegisterStoreRequest;
use App\Http\Responses\JsonDataResponse;
use Illuminate\Http\JsonResponse;

final readonly class RegisterStoreController
{
    public function __construct(
        private RegisterStore $registerStore,
    ) {}

    public function __invoke(RegisterStoreRequest $request): JsonResponse
    {
        $result = $this->registerStore->handle(
            payload: $request->payload(),
            image: $request->file('image'),
        );

        return (new JsonDataResponse(
            data: [
                'access_token' => $result['token'],
                'token_type'   => 'bearer',
                'expires_in'   => auth('api')->factory()->getTTL() * 60,
                'user'         => $result['user'],
                'store'        => $result['store'],
                'message'      => 'Store registered. Pending admin approval.',
            ],
            status: 201,
        ))->toResponse($request);
    }
}
