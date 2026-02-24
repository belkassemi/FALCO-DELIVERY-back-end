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
                'orderId' => $order->id,
                'status'  => $order->status,
                'message' => 'Order placed successfully'
            ], 201);
        });
    }

    public function cancel($id)
    {
        $order = Order::findOrFail($id);
        
        $this->authorize('cancel', $order);

        $order->update(['status' => 'cancelled']);
        $this->logService->log('order_cancelled', $order);

        return response()->json(['message' => 'Order cancelled successfully']);
    }

    public function track($id)
    {
        // TECHNICAL CONSTRAINT: Use withCoordinates scope for spatial extraction
        $order = Order::with(['courier.courierLocation' => function($q) {
            $q->withCoordinates();
        }])->findOrFail($id);
        
        return response()->json([
            'status' => $order->status,
            'courierLocation' => ($order->courier && $order->courier->courierLocation) ? [
                'lat' => $order->courier->courierLocation->lat_val,
                'lng' => $order->courier->courierLocation->lng_val,
            ] : null
        ]);
    }

    public function history()
    {
        return response()->json(auth('api')->user()->orders()->with('restaurant')->latest()->get());
    }
}
