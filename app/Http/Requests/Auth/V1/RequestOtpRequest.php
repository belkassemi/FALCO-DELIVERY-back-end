<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\V1;

use App\Http\Payloads\Auth\RequestOtpPayload;
use Illuminate\Foundation\Http\FormRequest;

final class RequestOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone_number' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9]{9,15}$/'],
        ];
    }

    public function payload(): RequestOtpPayload
    {
        return new RequestOtpPayload(
            phoneNumber: $this->string('phone_number')->toString(),
        );
    }
}
