<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Http\Payloads\Orders\StoreOrderPayload;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Services\DeliveryFeeService;
use App\Services\DispatchService;
use App\Services\LogService;
use Illuminate\Support\Facades\DB;

final readonly class CreateOrder
{
    public function __construct(
        private DeliveryFeeService $deliveryFeeService,
        private DispatchService $dispatchService,
        private LogService $logService,
    ) {}

    public function handle(StoreOrderPayload $payload, int $customerId): Order
    {
        return DB::transaction(function () use ($payload, $customerId): Order {
            $store = Store::findOrFail($payload->storeId);

            if (!$store->is_approved) {
                throw new \DomainException('This store is not available.');
            }

            if (!$store->isCurrentlyOpen() && !$store->is_open) {
                throw new \DomainException('This store is currently closed.');
            }

            $totalPrice = 0;
            $orderItems = [];
            $hasAgeRestricted = false;

            foreach ($payload->items as $itemData) {
                $product = Product::findOrFail($itemData['id']);

                if ($product->is_age_restricted) {
                    $hasAgeRestricted = true;
                }

                $totalPrice += $product->price * $itemData['qty'];
                $orderItems[] = [
                    'product_id' => $product->id,
                    'quantity'   => $itemData['qty'],
                    'price'      => $product->price,
                ];
            }

            if ($hasAgeRestricted && !$payload->ageConfirmation) {
                throw new \DomainException('AGE_CONFIRMATION_REQUIRED');
            }

            $address = Address::findOrFail($payload->addressId);
            $feeData = $this->deliveryFeeService->calculate($store, $address);

            $order = Order::create([
                'customer_id'          => $customerId,
                'store_id'             => $payload->storeId,
                'address_id'           => $payload->addressId,
                'total_price'          => $totalPrice,
                'delivery_fee'         => $feeData['fee'],
                'delivery_distance_km' => $feeData['distance_km'],
                'status'               => 'pending',
                'age_confirmation'     => $hasAgeRestricted,
                'age_confirmation_at'  => $hasAgeRestricted ? now() : null,
            ]);

            $order->items()->createMany($orderItems);

            $this->logService->log('order_created', $order);
            $this->dispatchService->dispatchOrder($order);

            return $order;
        });
    }
}
