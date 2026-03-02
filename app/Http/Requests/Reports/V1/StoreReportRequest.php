<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports\V1;

use App\Http\Payloads\Reports\StoreReportPayload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id'    => ['required', 'integer', 'exists:orders,id'],
            'type'        => [
                'required', 
                'string',
                Rule::in([
                    'late_delivery',
                    'courier_no_show',
                    'missing_items',
                    'wrong_items',
                    'courier_behavior',
                    'store_issue',
                    'damaged_product',
                    'other'
                ])
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function payload(): StoreReportPayload
    {
        return new StoreReportPayload(
            orderId: (int) $this->input('order_id'),
            type: $this->input('type'),
            description: $this->input('description'),
        );
    }
}
