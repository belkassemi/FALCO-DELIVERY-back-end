<?php

declare(strict_types=1);

namespace App\Http\Controllers\Categories\V1;

use App\Http\Responses\JsonDataResponse;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class IndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        return (new JsonDataResponse(
            data: Category::where('is_active', true)->orderBy('sort_order')->get(),
        ))->toResponse($request);
    }
}
