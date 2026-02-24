<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\CourierLocation;
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
            ->with('restaurant', 'customer')
            ->latest()
            ->paginate(15);
            
        return response()->json($orders);
    }
    /**
     * Update courier online status.
     */
    public function updateStatus(Request $request)
    {
        $request->validate(['online' => 'required|boolean']);
        
        $location = auth('api')->user()->courierLocation;
        $location->update(['is_online' => $request->online]);

        return response()->json(['message' => 'Status updated', 'is_online' => $request->online]);
    }

    /**
     * Update real-time coordinates.
     */
    public function updateLocation(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $courier = auth('api')->user();
        $location = $courier->courierLocation;
        // TECHNICAL CONSTRAINT: Must use ST_SetSRID and ST_MakePoint for geography POINT
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

        return response()->json(['message' => 'Location updated']);
    }

    public function acceptOrder($id)
    {
        $order = Order::findOrFail($id);
        
        if ($order->status !== 'pending') {
            return response()->json(['error' => 'Order cannot be accepted because it is no longer pending.'], 400);
        }

        if ($order->courier_id !== null) {
            return response()->json(['error' => 'Order already accepted by another courier.'], 400);
        }

        $order->update([
            'courier_id' => auth('api')->id(),
            'status'     => 'assigned'
        ]);

        // Mark the dispatch log as accepted
        OrderDispatchLog::where('order_id', $id)
            ->where('courier_id', auth('api')->id())
            ->update(['status' => 'accepted', 'responded_at' => now()]);

        broadcast(new OrderAssignedEvent($order));

        return response()->json([
            'message' => 'Order assigned successfully',
            'orderStatus' => 'assigned'
        ]);
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
        return response()->json(['message' => 'Order delivered', 'status' => 'delivered']);
    }

    public function earnings()
    {
        $earnings = auth('api')->user()->courierOrders()
            ->where('status', 'delivered')
            ->sum('delivery_fee');

        return response()->json(['total_earnings' => $earnings]);
    }
}
