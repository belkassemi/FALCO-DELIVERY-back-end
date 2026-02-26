<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\CourierLocation;
use App\Models\CourierEarning;
use App\Models\OrderDispatchLog;
use App\Events\OrderAssignedEvent;
use App\Events\OrderLocationUpdateEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class CourierController extends Controller
{
    public function history()
    {
        $orders = auth('api')->user()->courierOrders()
            ->with('store', 'customer')
            ->latest()
            ->paginate(15);

        return response()->json($orders);
    }

    public function updateStatus(Request $request)
    {
        $request->validate(['online' => 'required|boolean']);

        $location = auth('api')->user()->courierLocation;
        $location->update(['is_online' => $request->online]);

        return response()->json(['message' => 'Status updated', 'is_online' => $request->online]);
    }

    public function updateLocation(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $courier = auth('api')->user();

        DB::statement(
            "UPDATE courier_locations SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, updated_at = NOW() WHERE courier_id = ?",
            [$request->lng, $request->lat, $courier->id]
        );

        // Broadcast to customers tracking active orders
        $activeOrders = Order::where('courier_id', $courier->id)
            ->whereIn('status', ['assigned', 'preparing', 'on_the_way'])
            ->get();

        foreach ($activeOrders as $order) {
            broadcast(new OrderLocationUpdateEvent($order->id, $request->lat, $request->lng));
        }

        return response()->json(['message' => 'Location updated successfully']);
    }

    public function acceptOrder($id)
    {
        return DB::transaction(function () use ($id) {
            $order = Order::lockForUpdate()->findOrFail($id);

            if ($order->status !== 'pending') {
                return response()->json(['error' => 'Order cannot be accepted because it is no longer pending.'], 400);
            }
            if ($order->courier_id !== null) {
                return response()->json(['error' => 'Order already accepted by another courier.'], 409);
            }

            $order->update([
                'courier_id' => auth('api')->id(),
                'status'     => 'assigned'
            ]);

            OrderDispatchLog::where('order_id', $id)
                ->where('courier_id', auth('api')->id())
                ->update(['status' => 'accepted', 'responded_at' => now()]);

            broadcast(new OrderAssignedEvent($order));

            return response()->json([
                'message'     => 'Order assigned successfully',
                'orderStatus' => 'assigned'
            ]);
        });
    }

    public function pickupOrder($id)
    {
        $order = Order::findOrFail($id);
        $order->update(['status' => 'on_the_way']);
        return response()->json(['message' => 'Order picked up', 'status' => 'on_the_way']);
    }

    public function deliverOrder($id)
    {
        $order = Order::findOrFail($id);
        $order->update(['status' => 'delivered', 'payment_status' => 'paid']);

        // Record courier earnings (PRD §8.3 — cash-based, for visibility)
        CourierEarning::create([
            'courier_id' => auth('api')->id(),
            'order_id'   => $order->id,
            'amount'     => $order->delivery_fee,
            'type'       => 'delivery',
        ]);

        return response()->json(['message' => 'Order delivered', 'status' => 'delivered']);
    }

    public function rejectOrder($id)
    {
        $courierId = auth('api')->id();

        OrderDispatchLog::where('order_id', $id)
            ->where('courier_id', $courierId)
            ->update(['status' => 'rejected', 'responded_at' => now()]);

        return response()->json(['message' => 'Order rejected.']);
    }

    public function availableOrders()
    {
        $orders = Order::where('status', 'pending')
            ->whereNull('courier_id')
            ->with('store', 'customer')
            ->latest()
            ->get();

        return response()->json($orders);
    }

    /**
     * Courier earnings visibility (PRD §8.3 — no wallet, cash-based).
     */
    public function earnings()
    {
        $courier = auth('api')->user();
        $earnings = CourierEarning::where('courier_id', $courier->id)
            ->latest()
            ->paginate(20);

        $totalEarnings = CourierEarning::where('courier_id', $courier->id)->sum('amount');

        return response()->json([
            'total_earnings' => $totalEarnings,
            'history'        => $earnings,
        ]);
    }
}
