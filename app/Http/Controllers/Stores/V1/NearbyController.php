<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stores\V1;

use App\Http\Responses\JsonDataResponse;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class NearbyController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => ['required', 'numeric'],
            'lng' => ['required', 'numeric'],
        ]);

        $stores = Store::withCoordinates()
            ->where('is_approved', true)
            ->nearby((float) $request->input('lat'), (float) $request->input('lng'), 10)
            ->get();

        return (new JsonDataResponse(data: $stores))->toResponse($request);
    }
}
