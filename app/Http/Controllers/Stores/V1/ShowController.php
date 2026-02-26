<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stores\V1;

use App\Http\Responses\JsonDataResponse;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ShowController
{
    public function __invoke(Request $request, int $id): JsonResponse
    {
        $store = Store::withCoordinates()
            ->with(['products' => fn ($q) => $q->where('is_available', true), 'hours', 'categoryRelation'])
            ->findOrFail($id);

        return (new JsonDataResponse(
            data: array_merge($store->toArray(), [
                'is_currently_open' => $store->isCurrentlyOpen(),
            ]),
        ))->toResponse($request);
    }
}
