<?php

namespace App\Services;

use App\Models\Order;
use App\Models\CourierLocation;
use App\Models\OrderDispatchLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DispatchService
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Start the dispatch process for a new order.
     */
    public function dispatchOrder(Order $order)
    {
        // 1. Find potential couriers (online, within radius, sorted by distance)
        $restaurant = \App\Models\Restaurant::withCoordinates()
            ->select('id')
            ->selectRaw('ST_Y(location::geometry) as lat_val')
            ->selectRaw('ST_X(location::geometry) as lng_val')
            ->find($order->restaurant_id);

        if (!$restaurant || $restaurant->lat_val === null || $restaurant->lng_val === null) {
            Log::error("Restaurant {$order->restaurant_id} is missing coordinates for Order #{$order->id}");
            return false;
        }

        $couriers = $this->findNearbyCouriers($restaurant->lat_val, $restaurant->lng_val);

        if ($couriers->isEmpty()) {
            Log::warning("No couriers available for Order #{$order->id}");
            return false;
        }

        // 2. Start sequential assignment
        $this->assignToNextAvailableCourier($order, $couriers);
        return true;
    }

    /**
     * Finds online couriers near a specific coordinate.
     */
    public function findNearbyCouriers(float $lat, float $lng, float $radiusKm = 10)
    {
        // TECHNICAL CONSTRAINT: Use ST_DWithin and KNN operator via Model Scope
        return CourierLocation::withCoordinates()
            ->nearestTo($lat, $lng, $radiusKm)
            ->get();
    }

    /**
     * Assignment logic: send request to one courier at a time.
     */
    public function assignToNextAvailableCourier(Order $order, $couriers)
    {
        // Find the first courier who hasn't timed out or rejected yet for this order
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
            "You have a new order request from {$order->restaurant->name}.",
            ['order_id' => $order->id]
        );

        // Dispatch a delayed job to handle timeout after 20 seconds
        \App\Jobs\AssignOrderTimeoutJob::dispatch($order, $nextCourier->courier_id)->delay(now()->addSeconds(20));
    }
}
