<?php

declare(strict_types=1);

namespace App\Http\Requests\Orders\V1;

use App\Http\Payloads\Orders\StoreOrderPayload;
use Illuminate\Foundation\Http\FormRequest;

final class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id'         => ['required', 'exists:stores,id'],
            'address_id'       => ['required', 'exists:addresses,id'],
            'items'            => ['required', 'array', 'min:1'],
            'items.*.id'       => ['required', 'exists:products,id'],
            'items.*.qty'      => ['required', 'integer', 'min:1'],
            'age_confirmation' => ['sometimes', 'boolean'],
        ];
    }

    public function payload(): StoreOrderPayload
    {
        return new StoreOrderPayload(
            storeId: (int) $this->input('store_id'),
            addressId: (int) $this->input('address_id'),
            items: $this->input('items'),
            ageConfirmation: $this->boolean('age_confirmation'),
        );
    }
}
