<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier\V1;

use App\Http\Responses\JsonDataResponse;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class AvailableOrdersController
{
    public function __invoke(Request $request): JsonResponse
    {
        $orders = Order::where('status', 'pending')
            ->whereNull('courier_id')
            ->with('store', 'customer')
            ->latest()
            ->get();

        return (new JsonDataResponse(data: $orders))->toResponse($request);
    }
}
