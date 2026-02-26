<?php

declare(strict_types=1);

namespace App\Http\Controllers\Orders\V1;

use App\Actions\Orders\CreateOrder;
use App\Http\Requests\Orders\V1\StoreOrderRequest;
use App\Http\Responses\JsonDataResponse;
use App\Http\Responses\JsonErrorResponse;
use Illuminate\Http\JsonResponse;

final readonly class StoreController
{
    public function __construct(
        private CreateOrder $createOrder,
    ) {}

    public function __invoke(StoreOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->createOrder->handle(
                payload: $request->payload(),
                customerId: auth('api')->id(),
            );

            return (new JsonDataResponse(
                data: [
                    'order_id'     => $order->id,
                    'status'       => $order->status,
                    'total_price'  => $order->total_price,
                    'delivery_fee' => $order->delivery_fee,
                    'distance_km'  => $order->delivery_distance_km,
                ],
                status: 201,
                meta: ['message' => 'Order created successfully'],
            ))->toResponse($request);
        } catch (\DomainException $e) {
            if ($e->getMessage() === 'AGE_CONFIRMATION_REQUIRED') {
                return (new JsonErrorResponse(
                    title: 'Age Confirmation Required',
                    detail: 'This order contains age-restricted products. Please confirm you are of legal age.',
                    status: 422,
                    errors: ['requires_age_confirmation' => true],
                ))->toResponse($request);
            }

            return (new JsonErrorResponse(
                title: 'Order Failed',
                detail: $e->getMessage(),
                status: 400,
            ))->toResponse($request);
        }
    }
}
