<?php

declare(strict_types=1);

namespace App\Http\Controllers\Orders\V1;

use App\Actions\Orders\ReorderFromPast;
use App\Http\Responses\JsonDataResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ReorderController
{
    public function __construct(
        private ReorderFromPast $reorderFromPast,
    ) {}

    public function __invoke(Request $request, int $id): JsonResponse
    {
        $result = $this->reorderFromPast->handle(
            orderId: $id,
            customerId: auth('api')->id(),
        );

        return (new JsonDataResponse(data: $result))->toResponse($request);
    }
}
