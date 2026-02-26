<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\Store;
use App\Models\Address;
use App\Services\DispatchService;
use App\Services\LogService;
use App\Services\DeliveryFeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    protected DispatchService $dispatchService;
    protected LogService $logService;
    protected DeliveryFeeService $deliveryFeeService;

    public function __construct(
        DispatchService $dispatchService,
        LogService $logService,
        DeliveryFeeService $deliveryFeeService
    ) {
        $this->dispatchService    = $dispatchService;
        $this->logService         = $logService;
        $this->deliveryFeeService = $deliveryFeeService;
    }

    public function store(Request $request)
    {
        $request->validate([
            'store_id'          => 'required|exists:stores,id',
            'address_id'        => 'required|exists:addresses,id',
            'items'             => 'required|array|min:1',
            'items.*.id'        => 'required|exists:products,id',
            'items.*.qty'       => 'required|integer|min:1',
            'age_confirmation'  => 'sometimes|boolean',
        ]);

        return DB::transaction(function () use ($request) {
            $totalPrice = 0;
            $orderItems = [];
            $hasAgeRestricted = false;

            // Store guards: must be approved and currently open
            $store = Store::findOrFail($request->store_id);
            if (!$store->is_approved) {
                return response()->json(['error' => 'This store is not available.'], 400);
            }
            if (!$store->isCurrentlyOpen() && !$store->is_open) {
                return response()->json(['error' => 'This store is currently closed.'], 400);
            }
            if ($store->owner && $store->owner->status !== 'active') {
                return response()->json(['error' => 'This store is not available.'], 400);
            }

            foreach ($request->items as $itemData) {
                $product = Product::findOrFail($itemData['id']);

                // Check age restriction
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

            // PRD: age-restricted products require explicit self-declaration
            if ($hasAgeRestricted && !$request->boolean('age_confirmation')) {
                return response()->json([
                    'requires_age_confirmation' => true,
                    'message' => 'This order contains age-restricted products. Please confirm you are of legal age.',
                ], 422);
            }

            // Calculate delivery fee using Haversine
            $address = Address::findOrFail($request->address_id);
            $feeData = $this->deliveryFeeService->calculate($store, $address);

            $order = Order::create([
                'customer_id'         => auth('api')->id(),
                'store_id'            => $request->store_id,
                'address_id'          => $request->address_id,
                'total_price'         => $totalPrice,
                'delivery_fee'        => $feeData['fee'],
                'delivery_distance_km' => $feeData['distance_km'],
                'status'              => 'pending',
                'age_confirmation'    => $hasAgeRestricted ? true : false,
                'age_confirmation_at' => $hasAgeRestricted ? now() : null,
            ]);

            $order->items()->createMany($orderItems);

            $this->logService->log('order_created', $order);
            $this->dispatchService->dispatchOrder($order);

            return response()->json([
                'order_id'       => $order->id,
                'status'         => $order->status,
                'total_price'    => $order->total_price,
                'delivery_fee'   => $order->delivery_fee,
                'distance_km'    => $order->delivery_distance_km,
                'message'        => 'Order created successfully'
            ], 201);
        });
    }

    /**
     * Reorder shortcut: clones items from a past order.
     */
    public function reorder($id)
    {
        $pastOrder = Order::with('items.product')
            ->where('customer_id', auth('api')->id())
            ->findOrFail($id);

        $unavailable = [];
        $available = [];

        foreach ($pastOrder->items as $item) {
            if (!$item->product || !$item->product->is_available || $item->product->trashed()) {
                $unavailable[] = [
                    'product_id' => $item->product_id,
                    'name'       => $item->product?->name ?? 'Removed product',
                ];
            } else {
                $available[] = [
                    'id'  => $item->product_id,
                    'qty' => $item->quantity,
                ];
            }
        }

        return response()->json([
            'store_id'    => $pastOrder->store_id,
            'items'       => $available,
            'unavailable' => $unavailable,
            'message'     => count($unavailable) > 0
                ? 'Some items from your previous order are no longer available.'
                : 'All items available. Ready to reorder.',
        ]);
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
        $order = Order::with(['courier.courierLocation' => function($q) {
            $q->withCoordinates();
        }])->findOrFail($id);

        return response()->json([
            'order_id' => $order->id,
            'status'   => $order->status,
            'courierLocation' => ($order->courier && $order->courier->courierLocation) ? [
                'lat' => $order->courier->courierLocation->lat_val,
                'lng' => $order->courier->courierLocation->lng_val,
            ] : null
        ]);
    }

    public function show($id)
    {
        $order = Order::with(['items.product', 'store', 'courier'])
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

    public function history()
    {
        return response()->json(
            auth('api')->user()->orders()->with('store')->latest()->paginate(20)
        );
    }
}
