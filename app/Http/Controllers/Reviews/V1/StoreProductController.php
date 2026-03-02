<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reviews\V1;

use App\Actions\Reviews\CreateProductReviews;
use App\Http\Requests\Reviews\V1\StoreProductReviewRequest;
use App\Http\Responses\JsonDataResponse;
use Illuminate\Http\JsonResponse;

final readonly class StoreProductController
{
    public function __construct(
        private CreateProductReviews $createProductReviews,
    ) {}

    public function __invoke(StoreProductReviewRequest $request): JsonResponse
    {
        $reviews = $this->createProductReviews->handle(
            payload: $request->payload(),
            userId: (int) auth('api')->id(),
        );

        return new JsonDataResponse(
            data: $reviews,
            status: 201,
        );
    }
}
