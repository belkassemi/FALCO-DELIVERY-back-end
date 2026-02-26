<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

final readonly class JsonDataResponse implements Responsable
{
    public function __construct(
        private mixed $data,
        private int $status = 200,
        private array $meta = [],
    ) {}

    public function toResponse($request): JsonResponse
    {
        $response = ['data' => $this->data];

        if (!empty($this->meta)) {
            $response['meta'] = $this->meta;
        }

        return new JsonResponse($response, $this->status);
    }
}
