<?php

declare(strict_types=1);

namespace App\Http\Requests\Reviews\V1;

use App\Http\Payloads\Reviews\StoreStoreReviewPayload;
use Illuminate\Foundation\Http\FormRequest;

final class StoreStoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'rating'   => ['required', 'integer', 'min:1', 'max:5'],
            'comment'  => ['nullable', 'string', 'max:500'], // PRD max 500 chars for store review
        ];
    }

    public function payload(): StoreStoreReviewPayload
    {
        return new StoreStoreReviewPayload(
            storeId: (int) $this->input('store_id'),
            rating: (int) $this->input('rating'),
            comment: $this->input('comment'),
        );
    }
}
