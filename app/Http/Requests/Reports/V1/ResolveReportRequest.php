<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports\V1;

use App\Http\Payloads\Reports\ResolveReportPayload;
use Illuminate\Foundation\Http\FormRequest;

final class ResolveReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'admin_response' => ['required', 'string', 'max:1000'],
            'action_taken'   => ['required', 'string', 'max:255'],
        ];
    }

    public function payload(): ResolveReportPayload
    {
        return new ResolveReportPayload(
            adminResponse: $this->input('admin_response'),
            actionTaken: $this->input('action_taken'),
        );
    }
}
