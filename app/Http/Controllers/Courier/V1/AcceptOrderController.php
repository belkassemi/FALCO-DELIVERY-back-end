<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier\V1;

use App\Events\OrderAssignedEvent;
use App\Http\Responses\JsonDataResponse;
use App\Http\Responses\JsonErrorResponse;
use App\Models\Order;
use App\Models\OrderDispatchLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class AcceptOrderController
{
    public function __invoke(Request $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id): JsonResponse {
            $order = Order::lockForUpdate()->findOrFail($id);

            if ($order->status !== 'pending') {
                return (new JsonErrorResponse(
                    title: 'Cannot Accept',
                    detail: 'Order is no longer pending.',
                    status: 400,
                ))->toResponse($request);
            }

            if ($order->courier_id !== null) {
                return (new JsonErrorResponse(
                    title: 'Already Accepted',
                    detail: 'Order already accepted by another courier.',
                    status: 409,
                ))->toResponse($request);
            }

            $order->update([
                'courier_id' => auth('api')->id(),
                'status'     => 'assigned',
            ]);

            OrderDispatchLog::where('order_id', $id)
                ->where('courier_id', auth('api')->id())
                ->update(['status' => 'accepted', 'responded_at' => now()]);

            broadcast(new OrderAssignedEvent($order));

            return (new JsonDataResponse(
                data: ['status' => 'assigned'],
                meta: ['message' => 'Order assigned successfully'],
            ))->toResponse($request);
        });
    }
}
