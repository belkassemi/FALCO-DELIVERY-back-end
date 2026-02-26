<?php

declare(strict_types=1);

namespace App\Http\Controllers\Orders\V1;

use App\Http\Responses\JsonDataResponse;
use App\Http\Responses\JsonErrorResponse;
use App\Models\Order;
use App\Services\LogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class CancelController
{
    public function __construct(
        private LogService $logService,
    ) {}

    public function __invoke(Request $request, int $id): JsonResponse
    {
        $order = Order::where('customer_id', auth('api')->id())->findOrFail($id);

        if (!$order->canBeCancelled()) {
            return (new JsonErrorResponse(
                title: 'Cannot Cancel',
                detail: 'Order can only be cancelled while pending.',
                status: 400,
            ))->toResponse($request);
        }

        $order->update(['status' => 'cancelled']);
        $this->logService->log('order_cancelled', $order);

        return (new JsonDataResponse(
            data: ['status' => 'cancelled'],
            meta: ['message' => 'Order cancelled successfully'],
        ))->toResponse($request);
    }
}
