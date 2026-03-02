<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports\V1;

use App\Http\Responses\JsonDataResponse;
use App\Models\OrderReport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class IndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        // GET /api/admin/reports -> Admin paginated view with support for filtering
        // We use basic spati/query-builder to allow ?filter[status]=open&filter[type]=late_delivery
        $reports = QueryBuilder::for(OrderReport::class)
            ->allowedFilters(['status', 'type'])
            ->with(['user:id,full_name,phone_number', 'order']) // Load helpful relations
            ->latest()
            ->paginate(15);

        return new JsonDataResponse(
            data: $reports,
        );
    }
}
