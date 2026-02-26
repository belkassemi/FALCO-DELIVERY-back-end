<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier\V1;

use App\Http\Responses\JsonDataResponse;
use App\Models\CourierEarning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class EarningsController
{
    public function __invoke(Request $request): JsonResponse
    {
        $courierId = auth('api')->id();

        $earnings = CourierEarning::where('courier_id', $courierId)
            ->latest()
            ->paginate(20);

        $totalEarnings = CourierEarning::where('courier_id', $courierId)->sum('amount');

        return (new JsonDataResponse(
            data: $earnings,
            meta: ['total_earnings' => (float) $totalEarnings],
        ))->toResponse($request);
    }
}
