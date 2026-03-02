<?php

declare(strict_types=1);

namespace App\Http\Requests\Reviews\V1;

use App\Http\Payloads\Reviews\ProductReviewItemPayload;
use App\Http\Payloads\Reviews\StoreProductReviewPayload;
use Illuminate\Foundation\Http\FormRequest;

final class StoreProductReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled via sanctum middleware on route
    }

    public function rules(): array
    {
        return [
            'order_id'            => ['required', 'integer', 'exists:orders,id'],
            'reviews'             => ['required', 'array', 'min:1'],
            'reviews.*.order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'reviews.*.rating'    => ['required', 'integer', 'min:1', 'max:5'],
            'reviews.*.comment'   => ['nullable', 'string', 'max:300'], // PRD max 300 chars for product
        ];
    }

    public function payload(): StoreProductReviewPayload
    {
        $reviewsPayload = array_map(function (array $review) {
            return new ProductReviewItemPayload(
                orderItemId: (int) $review['order_item_id'],
                rating: (int) $review['rating'],
                comment: $review['comment'] ?? null,
            );
        }, $this->input('reviews'));

        return new StoreProductReviewPayload(
            orderId: (int) $this->input('order_id'),
            reviews: $reviewsPayload,
        );
    }
}
