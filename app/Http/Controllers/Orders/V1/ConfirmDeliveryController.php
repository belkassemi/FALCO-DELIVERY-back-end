<?php

declare(strict_types=1);

namespace App\Http\Controllers\Orders\V1;

use App\Http\Responses\JsonDataResponse;
use App\Http\Responses\JsonErrorResponse;
use App\Models\Order;
use App\Services\LogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ConfirmDeliveryController
{
    public function __construct(
        private LogService $logService,
    ) {}

    public function __invoke(Request $request, int $id): JsonResponse
    {
        $order = Order::where('customer_id', auth('api')->id())->findOrFail($id);

        if ($order->status !== 'on_the_way') {
            return (new JsonErrorResponse(
                title: 'Invalid Status',
                detail: 'Order must be on the way before confirming delivery.',
                status: 400,
            ))->toResponse($request);
        }

        $order->update(['status' => 'delivered', 'payment_status' => 'paid']);
        $this->logService->log('delivery_confirmed', $order);

        return (new JsonDataResponse(
            data: ['status' => 'delivered'],
            meta: ['message' => 'Delivery confirmed. Thank you!'],
        ))->toResponse($request);
    }
}
