<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stores\V1;

use App\Http\Responses\JsonDataResponse;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class IndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = Store::withCoordinates()->where('is_approved', true);

        if ($request->has('category_id')) {
            $query->where('category_id', (int) $request->input('category_id'));
        }

        if ($request->has('rating')) {
            $query->where('rating', '>=', (float) $request->input('rating'));
        }

        return (new JsonDataResponse(data: $query->paginate(15)))->toResponse($request);
    }
}
