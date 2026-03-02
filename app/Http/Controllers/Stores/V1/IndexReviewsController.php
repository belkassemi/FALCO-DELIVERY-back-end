<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stores\V1;

use App\Http\Responses\JsonDataResponse;
use App\Models\Review;
use Illuminate\Http\JsonResponse;

final readonly class IndexReviewsController
{
    public function __invoke(int $id): JsonResponse
    {
        // GET /api/stores/{id}/reviews -> Paginated public reviews for a specific store
        $reviews = Review::query()
            ->with('user:id,full_name,avatar') // Only load necessary public fields
            ->where('store_id', $id)
            ->where('type', 'store')
            ->latest()
            ->paginate(15);

        return new JsonDataResponse(
            data: $reviews,
        );
    }
}
