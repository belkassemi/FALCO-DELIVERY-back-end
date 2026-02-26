<?php

declare(strict_types=1);

namespace App\Http\Controllers\Orders\V1;

use App\Http\Responses\JsonDataResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class IndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $orders = auth('api')->user()->orders()
            ->with('store')
            ->latest()
            ->paginate(20);

        return (new JsonDataResponse(data: $orders))->toResponse($request);
    }
}
