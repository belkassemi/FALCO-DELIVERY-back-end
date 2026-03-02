<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reviews\V1;

use App\Actions\Reviews\DeleteReview;
use Illuminate\Http\JsonResponse;

final readonly class DestroyController
{
    public function __construct(
        private DeleteReview $deleteReview,
    ) {}

    public function __invoke(int $id): JsonResponse
    {
        $this->deleteReview->handle($id);

        return response()->json([
            'message' => 'Review successfully removed.',
        ], 200); // Admin endpoints often use simple response, but standard json works.
    }
}
