<?php

declare(strict_types=1);

namespace App\Http\Payloads\Auth;

final readonly class RegisterStorePayload
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public string $password,
        public string $storeName,
        public int $categoryId,
        public ?string $address,
        public float $lat,
        public float $lng,
        public ?string $description,
    ) {}

    public function toUserArray(): array
    {
        return [
            'name'     => $this->name,
            'email'    => $this->email,
            'phone'    => $this->phone,
            'password' => $this->password,
            'role'     => 'restaurant_owner',
        ];
    }

    public function toStoreArray(int $userId): array
    {
        return [
            'user_id'     => $userId,
            'category_id' => $this->categoryId,
            'name'        => $this->storeName,
            'address'     => $this->address,
            'phone'       => $this->phone,
            'description' => $this->description,
            'is_approved' => false,
            'is_open'     => false,
        ];
    }
}
