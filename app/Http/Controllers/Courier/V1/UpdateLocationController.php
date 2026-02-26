<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier\V1;

use App\Events\OrderLocationUpdateEvent;
use App\Http\Responses\JsonDataResponse;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class UpdateLocationController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $courier = auth('api')->user();

        DB::statement(
            "UPDATE courier_locations SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, updated_at = NOW() WHERE courier_id = ?",
            [$request->input('lng'), $request->input('lat'), $courier->id]
        );

        $activeOrders = Order::where('courier_id', $courier->id)
            ->whereIn('status', ['assigned', 'preparing', 'on_the_way'])
            ->get();

        foreach ($activeOrders as $order) {
            broadcast(new OrderLocationUpdateEvent($order->id, $request->input('lat'), $request->input('lng')));
        }

        return (new JsonDataResponse(
            data: null,
            meta: ['message' => 'Location updated successfully'],
        ))->toResponse($request);
    }
}
