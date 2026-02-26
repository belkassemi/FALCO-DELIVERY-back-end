<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier\V1;

use App\Http\Responses\JsonDataResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class UpdateStatusController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['online' => ['required', 'boolean']]);

        $location = auth('api')->user()->courierLocation;
        $location->update(['is_online' => $request->boolean('online')]);

        return (new JsonDataResponse(
            data: ['is_online' => $request->boolean('online')],
            meta: ['message' => 'Status updated'],
        ))->toResponse($request);
    }
}
