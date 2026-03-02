<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports\V1;

use App\Http\Responses\JsonDataResponse;
use App\Models\OrderReport;
use Illuminate\Http\JsonResponse;

final readonly class IndexController
{
    public function __invoke(): JsonResponse
    {
        // GET /api/reports -> Customer views their own reports
        $reports = OrderReport::query()
            ->where('user_id', auth('api')->id())
            ->latest()
            ->get();

        return new JsonDataResponse(
            data: $reports,
        );
    }
}
