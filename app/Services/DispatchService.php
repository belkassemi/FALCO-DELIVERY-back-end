<?php

namespace App\Services;

use App\Models\Order;
use App\Models\CourierLocation;
use App\Models\OrderDispatchLog;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DispatchService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Start the dispatch process for a new order.
     * PRD: Courier assignment based on store coordinates (not customer address).
     */
    public function dispatchOrder(Order $order)
    {
        // Get store coordinates via PostGIS
        $store = Store::withCoordinates()
            ->select('id')
            ->selectRaw('ST_Y(location::geometry) as lat_val')
            ->selectRaw('ST_X(location::geometry) as lng_val')
            ->find($order->store_id);

        if (!$store || $store->lat_val === null || $store->lng_val === null) {
            Log::error("Store {$order->store_id} is missing coordinates for Order #{$order->id}");
            return false;
        }

        $couriers = $this->findNearbyCouriers($store->lat_val, $store->lng_val);

        if ($couriers->isEmpty()) {
            Log::warning("No couriers available for Order #{$order->id}");
            return false;
        }

        $this->assignToNextAvailableCourier($order, $couriers);
        return true;
    }

    /**
     * Finds online couriers near the store coordinates.
     */
    public function findNearbyCouriers(float $lat, float $lng, float $radiusKm = 10)
    {
        return CourierLocation::withCoordinates()
            ->nearestTo($lat, $lng, $radiusKm)
            ->get();
    }

    /**
     * Sequential assignment: send request to one courier at a time with 20-second timeout.
     */
    public function assignToNextAvailableCourier(Order $order, $couriers)
    {
        $attemptedCourierIds = OrderDispatchLog::where('order_id', $order->id)
            ->pluck('courier_id')
            ->toArray();

        $nextCourier = $couriers->whereNotIn('courier_id', $attemptedCourierIds)->first();

        if (!$nextCourier) {
            Log::info("Failover: No more couriers to try for Order #{$order->id}");
            return;
        }

        // Log the assignment attempt
        OrderDispatchLog::create([
            'order_id'   => $order->id,
            'courier_id' => $nextCourier->courier_id,
            'status'     => 'pending'
        ]);

        // Send notification
        $this->notificationService->sendToCourier(
            $nextCourier->courier,
            "New Order Request",
            "You have a new order request from {$order->store->name}.",
            ['order_id' => $order->id]
        );

        // Dispatch timeout job (20 seconds as per PRD)
        \App\Jobs\AssignOrderTimeoutJob::dispatch($order, $nextCourier->courier_id)->delay(now()->addSeconds(20));
    }
}
