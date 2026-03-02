<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reviews\V1;

use App\Actions\Reviews\CreateOrUpdateStoreReview;
use App\Http\Requests\Reviews\V1\StoreStoreReviewRequest;
use App\Http\Responses\JsonDataResponse;
use Illuminate\Http\JsonResponse;

final readonly class StoreStoreController
{
    public function __construct(
        private CreateOrUpdateStoreReview $createOrUpdateStoreReview,
    ) {}

    public function __invoke(StoreStoreReviewRequest $request): JsonResponse
    {
        $review = $this->createOrUpdateStoreReview->handle(
            payload: $request->payload(),
            userId: (int) auth('api')->id(),
        );

        return new JsonDataResponse(
            data: $review,
            status: 201,
        );
    }
}
