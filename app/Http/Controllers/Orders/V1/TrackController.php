<?php

declare(strict_types=1);

namespace App\Http\Controllers\Orders\V1;

use App\Http\Responses\JsonDataResponse;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class TrackController
{
    public function __invoke(Request $request, int $id): JsonResponse
    {
        $order = Order::with(['courier.courierLocation' => function ($q) {
            $q->withCoordinates();
        }])->findOrFail($id);

        $courierLocation = null;
        if ($order->courier && $order->courier->courierLocation) {
            $courierLocation = [
                'lat' => $order->courier->courierLocation->lat_val,
                'lng' => $order->courier->courierLocation->lng_val,
            ];
        }

        return (new JsonDataResponse(
            data: [
                'order_id'        => $order->id,
                'status'          => $order->status,
                'courierLocation' => $courierLocation,
            ],
        ))->toResponse($request);
    }
}
