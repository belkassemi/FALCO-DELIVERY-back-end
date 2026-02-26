<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier\V1;

use App\Http\Responses\JsonDataResponse;
use App\Models\CourierEarning;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class DeliverOrderController
{
    public function __invoke(Request $request, int $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $order->update(['status' => 'delivered', 'payment_status' => 'paid']);

        CourierEarning::create([
            'courier_id' => auth('api')->id(),
            'order_id'   => $order->id,
            'amount'     => $order->delivery_fee,
            'type'       => 'delivery',
        ]);

        return (new JsonDataResponse(
            data: ['status' => 'delivered'],
            meta: ['message' => 'Order delivered'],
        ))->toResponse($request);
    }
}
