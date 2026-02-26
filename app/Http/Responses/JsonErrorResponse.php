<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

final readonly class JsonErrorResponse implements Responsable
{
    public function __construct(
        private string $title,
        private string $detail,
        private int $status = 400,
        private array $errors = [],
    ) {}

    public function toResponse($request): JsonResponse
    {
        $response = [
            'type'   => 'about:blank',
            'title'  => $this->title,
            'status' => $this->status,
            'detail' => $this->detail,
        ];

        if (!empty($this->errors)) {
            $response['errors'] = $this->errors;
        }

        return new JsonResponse($response, $this->status, [
            'Content-Type' => 'application/problem+json',
        ]);
    }
}
