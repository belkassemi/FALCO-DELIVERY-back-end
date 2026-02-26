<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier\V1;

use App\Http\Responses\JsonDataResponse;
use App\Models\OrderDispatchLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class RejectOrderController
{
    public function __invoke(Request $request, int $id): JsonResponse
    {
        OrderDispatchLog::where('order_id', $id)
            ->where('courier_id', auth('api')->id())
            ->update(['status' => 'rejected', 'responded_at' => now()]);

        return (new JsonDataResponse(
            data: null,
            meta: ['message' => 'Order rejected.'],
        ))->toResponse($request);
    }
}
