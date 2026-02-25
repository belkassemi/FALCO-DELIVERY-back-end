<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\MenuItem;
use App\Models\OrderItem;
use App\Services\DispatchService;
use App\Services\LogService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    protected $dispatchService;
    protected $logService;

    public function __construct(DispatchService $dispatchService, LogService $logService)
    {
        $this->dispatchService = $dispatchService;
        $this->logService = $logService;
    }

    public function store(Request $request)
    {
        $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'address_id'    => 'required|exists:addresses,id',
            'items'         => 'required|array|min:1',
            'items.*.id'    => 'required|exists:menu_items,id',
            'items.*.qty'   => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($request) {
            $totalPrice = 0;
            $orderItems = [];

            // Restaurant guards: must be open and owner active
            $restaurant = \App\Models\Restaurant::findOrFail($request->restaurant_id);
            if (!$restaurant->is_open) {
                return response()->json(['error' => 'This restaurant is currently closed.'], 400);
            }
            if ($restaurant->owner && $restaurant->owner->status !== 'active') {
                return response()->json(['error' => 'This restaurant is not available.'], 400);
            }

            foreach ($request->items as $itemData) {
                $menuItem = MenuItem::findOrFail($itemData['id']);
                
                // Pharmacy check
                if ($menuItem->requires_prescription) {
                    return response()->json([
                        'requiresPrescription' => true,
                        'redirectUrl' => "https://wa.me/" . env('+212 694-288887') . "?text=Prescription for order"
                    ], 200);
                }

                $totalPrice += $menuItem->price * $itemData['qty'];
                $orderItems[] = [
                    'menu_item_id' => $menuItem->id,
                    'quantity'     => $itemData['qty'],
                    'price'        => $menuItem->price,
                ];
            }

            $order = Order::create([
                'customer_id'   => auth('api')->id(),
                'restaurant_id' => $request->restaurant_id,
                'address_id'    => $request->address_id,
                'total_price'   => $totalPrice,
                'delivery_fee'  => 10, // Static for now
                'status'        => 'pending',
            ]);

            $order->items()->createMany($orderItems);

            // Log activity
            $this->logService->log('order_created', $order);

            // Trigger Dispatch System
            $this->dispatchService->dispatchOrder($order);

            return response()->json([
                'order_id'    => $order->id,
                'status'      => $order->status,
                'total_price' => $order->total_price,
                'message'     => 'Order created successfully'
            ], 201);
        });
    }

    public function cancel($id)
    {
        $order = Order::findOrFail($id);
        
        $this->authorize('cancel', $order);

        $order->update(['status' => 'cancelled']);
        $this->logService->log('order_cancelled', $order);

        return response()->json([
            'message' => 'Order cancelled successfully',
            'status'  => 'cancelled'
        ]);
    }

    public function track($id)
    {
        // TECHNICAL CONSTRAINT: Use withCoordinates scope for spatial extraction
        $order = Order::with(['courier.courierLocation' => function($q) {
            $q->withCoordinates();
        }])->findOrFail($id);
        
        return response()->json([
            'order_id' => $order->id,
            'status' => $order->status,
            'courierLocation' => ($order->courier && $order->courier->courierLocation) ? [
                'lat' => $order->courier->courierLocation->lat_val,
                'lng' => $order->courier->courierLocation->lng_val,
            ] : null
        ]);
    }

    public function show($id)
    {
        $order = Order::with(['items.menuItem', 'restaurant', 'courier'])
            ->where('customer_id', auth('api')->id())
            ->findOrFail($id);

        return response()->json($order);
    }

    public function confirmDelivery($id)
    {
        $order = Order::where('customer_id', auth('api')->id())->findOrFail($id);

        if ($order->status !== 'on_the_way') {
            return response()->json(['error' => 'Order must be on the way before confirming delivery.'], 400);
        }

        $order->update(['status' => 'delivered', 'payment_status' => 'paid']);
        $this->logService->log('delivery_confirmed', $order);

        return response()->json(['message' => 'Delivery confirmed. Thank you!', 'status' => 'delivered']);
    }

    public function reportIssue(Request $request, $id)
    {
        $request->validate(['issue' => 'required|string|max:1000']);

        $order = Order::where('customer_id', auth('api')->id())->findOrFail($id);

        $this->logService->log('issue_reported', $order, ['issue' => $request->issue]);

        return response()->json(['message' => 'Issue reported successfully. Our team will review it shortly.']);
    }
}
