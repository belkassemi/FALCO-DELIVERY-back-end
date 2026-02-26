<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\V1;

use App\Http\Payloads\Auth\VerifyOtpPayload;
use Illuminate\Foundation\Http\FormRequest;

final class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone_number' => ['required', 'string', 'max:20'],
            'otp'          => ['required', 'string', 'size:6'],
            'full_name'    => ['nullable', 'string', 'max:255'],
            'tos_accepted' => ['required', 'accepted'],
        ];
    }

    public function payload(): VerifyOtpPayload
    {
        return new VerifyOtpPayload(
            phoneNumber: $this->string('phone_number')->toString(),
            otp: $this->string('otp')->toString(),
            fullName: $this->filled('full_name') ? $this->string('full_name')->toString() : null,
            tosAccepted: $this->boolean('tos_accepted'),
        );
    }
}
