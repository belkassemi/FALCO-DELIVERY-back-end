<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Models\Review;

final readonly class DeleteReview
{
    public function handle(int $reviewId): void
    {
        $review = Review::findOrFail($reviewId);
        $review->delete(); // Automatically handled via SoftDeletes on the Model
    }
}
