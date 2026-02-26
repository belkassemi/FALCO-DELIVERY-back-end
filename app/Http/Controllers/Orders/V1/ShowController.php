<?php

declare(strict_types=1);

namespace App\Http\Controllers\Orders\V1;

use App\Http\Responses\JsonDataResponse;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ShowController
{
    public function __invoke(Request $request, int $id): JsonResponse
    {
        $order = Order::with(['items.product', 'store', 'courier'])
            ->where('customer_id', auth('api')->id())
            ->findOrFail($id);

        return (new JsonDataResponse(data: $order))->toResponse($request);
    }
}
