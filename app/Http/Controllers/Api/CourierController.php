<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\CourierLocation;
use App\Models\CourierEarning;
use App\Models\CourierMonthlyStat;
use App\Models\OrderDispatchLog;
use App\Events\OrderAssignedEvent;
use App\Events\CourierLocationUpdated;
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

        // Broadcast to customer tracking their active order (PRD §8.2)
        $activeOrder = Order::where('courier_id', $courier->id)
            ->whereIn('status', ['courier_assigned', 'preparing', 'ready', 'picked_up'])
            ->first();

        if ($activeOrder) {
            broadcast(new CourierLocationUpdated(
                $activeOrder->id,
                $request->lat,
                $request->lng,
            ))->toOthers();
        }

        return response()->json(['message' => 'Location updated successfully']);
    }

    public function acceptOrder($id)
    {
        return DB::transaction(function () use ($id) {
            $order = Order::lockForUpdate()->findOrFail($id);

            // PRD §5.1: Must be in courier_searching status
            if ($order->status !== Order::STATUS_COURIER_SEARCHING) {
                return response()->json(['error' => 'Order is no longer available for acceptance.'], 400);
            }
            if ($order->courier_id !== null) {
                return response()->json(['error' => 'Order already accepted by another courier.'], 409);
            }

            $order->update([
                'courier_id' => auth('api')->id(),
                'status'     => Order::STATUS_COURIER_ASSIGNED,
            ]);

            OrderDispatchLog::where('order_id', $id)
                ->where('courier_id', auth('api')->id())
                ->update(['status' => 'accepted', 'responded_at' => now()]);

            broadcast(new OrderAssignedEvent($order));

            return response()->json([
                'message'     => 'Order assigned successfully',
                'orderStatus' => Order::STATUS_COURIER_ASSIGNED,
            ]);
        });
    }

    public function pickupOrder($id)
    {
        $order = Order::findOrFail($id);
        $order->update(['status' => 'picked_up']);
        return response()->json(['message' => 'Order picked up', 'status' => 'picked_up']);
    }

    public function deliverOrder($id)
    {
        $order = Order::findOrFail($id);

        if ($order->status !== Order::STATUS_PICKED_UP) {
            return response()->json(['error' => 'Order must be picked up before marking as delivered.'], 400);
        }

        $order->update(['status' => Order::STATUS_DELIVERED, 'payment_status' => 'paid']);

        // Record courier earnings (PRD §8.3 — cash-based, for visibility)
        CourierEarning::create([
            'courier_id' => auth('api')->id(),
            'order_id'   => $order->id,
            'amount'     => $order->delivery_fee,
            'type'       => 'delivery',
        ]);

        // PRD §8.4: Update courier monthly stats
        $month = now()->format('Y-m');
        $stat = CourierMonthlyStat::firstOrCreate(
            ['courier_id' => auth('api')->id(), 'month' => $month],
            ['total_deliveries' => 0, 'total_distance_km' => 0]
        );
        $stat->increment('total_deliveries');
        if ($order->delivery_distance_km) {
            $stat->increment('total_distance_km', $order->delivery_distance_km);
        }

        return response()->json(['message' => 'Order delivered', 'status' => Order::STATUS_DELIVERED]);
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
        // PRD §5.1: Only show orders in courier_searching status
        $orders = Order::where('status', Order::STATUS_COURIER_SEARCHING)
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

    /**
     * PUT /api/courier/orders/{id}/note
     * Courier can add/update note between courier_assigned and picked_up.
     */
    public function updateOrderNote(Request $request, $id)
    {
        $request->validate(['note' => 'required|string|max:500']);

        $order = Order::where('courier_id', auth('api')->id())->findOrFail($id);

        if (!in_array($order->status, ['courier_assigned', 'preparing', 'ready'])) {
            return response()->json(['error' => 'Notes can only be added once the order is assigned and before pickup.'], 400);
        }

        $order->update(['courier_note' => $request->note]);

        return response()->json(['message' => 'Note saved successfully.', 'courier_note' => $request->note]);
    }
}
