<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\V1;

use App\Http\Payloads\Auth\RegisterStorePayload;
use Illuminate\Foundation\Http\FormRequest;

final class RegisterStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'max:255', 'unique:users'],
            'phone'       => ['required', 'string', 'max:20', 'unique:users'],
            'password'    => ['required', 'string', 'min:6', 'confirmed'],
            'store_name'  => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'address'     => ['nullable', 'string', 'max:255'],
            'lat'         => ['required', 'numeric'],
            'lng'         => ['required', 'numeric'],
            'description' => ['nullable', 'string'],
            'image'       => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:5120'],
        ];
    }

    public function payload(): RegisterStorePayload
    {
        return new RegisterStorePayload(
            name: $this->string('name')->toString(),
            email: $this->string('email')->toString(),
            phone: $this->string('phone')->toString(),
            password: $this->string('password')->toString(),
            storeName: $this->string('store_name')->toString(),
            categoryId: (int) $this->input('category_id'),
            address: $this->filled('address') ? $this->string('address')->toString() : null,
            lat: (float) $this->input('lat'),
            lng: (float) $this->input('lng'),
            description: $this->filled('description') ? $this->string('description')->toString() : null,
        );
    }
}
