<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use App\Models\Store;
use App\Models\MenuChangeRequest;
use App\Models\StoreHour;
use App\Models\StoreClosure;
use App\Services\DispatchService;
use App\Services\NotificationService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class StoreDashboardController extends Controller
{
    protected DispatchService $dispatchService;
    protected NotificationService $notificationService;
    protected SmsService $smsService;

    public function __construct(
        DispatchService $dispatchService,
        NotificationService $notificationService,
        SmsService $smsService
    ) {
        $this->dispatchService     = $dispatchService;
        $this->notificationService = $notificationService;
        $this->smsService          = $smsService;
    }
    /**
     * Get the authenticated user's store with products and analytics.
     */
    public function index()
    {
        $store = auth('api')->user()->store()
            ->withCoordinates()
            ->with(['products', 'hours', 'closures', 'categoryRelation'])
            ->first();

        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        return response()->json(array_merge($store->toArray(), [
            'is_currently_open' => $store->isCurrentlyOpen(),
        ]));
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'name'        => 'required|string',
            'category_id' => 'nullable|exists:categories,id',
            'address'     => 'nullable|string',
            'lat'         => 'required|numeric',
            'lng'         => 'required|numeric',
            'phone'       => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $store = auth('api')->user()->store;
        $store->update($request->only(['name', 'category_id', 'address', 'phone', 'description']));

        DB::statement(
            "UPDATE stores SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, updated_at = NOW() WHERE id = ?",
            [$request->lng, $request->lat, $store->id]
        );

        return response()->json(['message' => 'Profile updated']);
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $store = auth('api')->user()->store;

        if ($request->hasFile('image')) {
            if ($store->image) {
                Storage::disk('public')->delete($store->image);
            }

            $path = $request->file('image')->store('stores', 'public');
            $fullUrl = url('storage/' . $path);
            $store->update(['image' => $fullUrl]);

            return response()->json([
                'message'   => 'Store image uploaded successfully',
                'image_url' => $fullUrl
            ]);
        }

        return response()->json(['error' => 'No image provided'], 400);
    }

    // --- Store Hours Management (PRD §7.1) ---

    public function getHours()
    {
        $store = auth('api')->user()->store;
        return response()->json($store->hours);
    }

    public function setHours(Request $request)
    {
        $request->validate([
            'hours'             => 'required|array|min:1',
            'hours.*.day_of_week' => 'required|integer|between:0,6',
            'hours.*.open_time'   => 'required|date_format:H:i',
            'hours.*.close_time'  => 'required|date_format:H:i',
            'hours.*.is_closed'   => 'sometimes|boolean',
        ]);

        $store = auth('api')->user()->store;

        foreach ($request->hours as $hour) {
            StoreHour::updateOrCreate(
                ['store_id' => $store->id, 'day_of_week' => $hour['day_of_week']],
                [
                    'open_time'  => $hour['open_time'],
                    'close_time' => $hour['close_time'],
                    'is_closed'  => $hour['is_closed'] ?? false,
                ]
            );
        }

        return response()->json(['message' => 'Store hours updated']);
    }

    public function addClosure(Request $request)
    {
        $request->validate([
            'closed_date' => 'required|date|after_or_equal:today',
            'reason'      => 'nullable|string',
        ]);

        $store = auth('api')->user()->store;

        StoreClosure::create([
            'store_id'    => $store->id,
            'closed_date' => $request->closed_date,
            'reason'      => $request->reason,
        ]);

        return response()->json(['message' => 'Closure date added']);
    }

    public function removeClosure($id)
    {
        $closure = StoreClosure::where('store_id', auth('api')->user()->store->id)->findOrFail($id);
        $closure->delete();
        return response()->json(['message' => 'Closure date removed']);
    }

    // --- Menu Management (PRD §7.2 — admin-approval workflow) ---

    public function getMenu()
    {
        $store = auth('api')->user()->store;
        return response()->json($store->products()->get());
    }

    public function addProduct(Request $request)
    {
        $request->validate([
            'name'        => 'required|string',
            'description' => 'nullable|string',
            'price'       => 'required|numeric',
            'category'    => 'nullable|string',
            'image'       => 'required|image|mimes:png|dimensions:min_width=100,min_height=100,max_width=2000,max_height=2000',
        ]);

        $store = auth('api')->user()->store;

        $data = $request->except('image');
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        MenuChangeRequest::create([
            'store_id'     => $store->id,
            'product_id'   => null,
            'action_type'  => 'create',
            'proposed_data' => $data,
            'old_data'     => null,
            'requested_by' => auth('api')->id(),
        ]);

        return response()->json([
            'message'        => 'Product creation request submitted',
            'request_status' => 'pending_admin_approval'
        ], 201);
    }

    public function updateProduct(Request $request, $id)
    {
        $item = Product::findOrFail($id);
        $this->authorize('manage', $item);

        MenuChangeRequest::create([
            'store_id'     => $item->store_id,
            'product_id'   => $item->id,
            'action_type'  => 'update',
            'proposed_data' => $request->all(),
            'old_data'     => $item->toArray(),
            'requested_by' => auth('api')->id(),
        ]);

        return response()->json(['message' => 'Product update request submitted for admin approval.']);
    }

    public function deleteProduct($id)
    {
        $item = Product::findOrFail($id);
        $this->authorize('manage', $item);

        MenuChangeRequest::create([
            'store_id'     => $item->store_id,
            'product_id'   => $item->id,
            'action_type'  => 'delete',
            'proposed_data' => null,
            'old_data'     => $item->toArray(),
            'requested_by' => auth('api')->id(),
        ]);

        return response()->json(['message' => 'Product deletion request submitted for admin approval.']);
    }

    // --- Order Management ---

    public function getOrders()
    {
        $store = auth('api')->user()->store;
        return response()->json($store->orders()->with('items.product', 'customer')->latest()->get());
    }

    /**
     * PRD §5.7: Store accepts order after verification call.
     * Transitions store_notified → store_confirmed → triggers courier dispatch.
     */
    public function acceptOrder($id)
    {
        $order = Order::findOrFail($id);
        $this->authorize('restaurantAction', $order);

        if ($order->status !== Order::STATUS_STORE_NOTIFIED) {
            return response()->json(['error' => 'Order can only be accepted when in store_notified status.'], 400);
        }

        $order->update(['status' => Order::STATUS_STORE_CONFIRMED]);

        // Notify customer that store confirmed
        if ($order->customer) {
            $this->notificationService->sendToCustomer(
                $order->customer,
                'Order Confirmed',
                'Your order has been confirmed by the store. Looking for a courier...',
                ['order_id' => $order->id, 'type' => 'store_confirmed']
            );
        }

        // PRD §5.7: NOW start courier search
        $order->update(['status' => Order::STATUS_COURIER_SEARCHING]);
        $dispatched = $this->dispatchService->dispatchOrder($order);

        if (!$dispatched) {
            $order->update(['status' => Order::STATUS_NO_COURIER_FOUND]);
            return response()->json([
                'message' => 'Order confirmed but no couriers available.',
                'status'  => Order::STATUS_NO_COURIER_FOUND,
            ]);
        }

        return response()->json([
            'message' => 'Order accepted. Courier search started.',
            'status'  => Order::STATUS_COURIER_SEARCHING,
        ]);
    }

    public function orderReady($id)
    {
        $order = Order::findOrFail($id);
        $this->authorize('restaurantAction', $order);
        $order->update(['status' => Order::STATUS_READY]);
        return response()->json(['message' => 'Order marked as ready for pickup', 'status' => 'ready']);
    }

    /**
     * PRD §5.7: Store rejects order after verification call.
     * Notifies customer via FCM + SMS.
     */
    public function rejectOrder($id)
    {
        $order = Order::with('customer')->findOrFail($id);
        $this->authorize('restaurantAction', $order);

        if (!in_array($order->status, [Order::STATUS_STORE_NOTIFIED, Order::STATUS_PENDING])) {
            return response()->json(['error' => 'Order can only be rejected before courier dispatch.'], 400);
        }

        $order->update(['status' => Order::STATUS_REJECTED]);

        // PRD §5.7: Notify customer via FCM + SMS
        if ($order->customer) {
            $this->notificationService->sendToCustomer(
                $order->customer,
                'Order Rejected',
                'Unfortunately, the store has rejected your order.',
                ['order_id' => $order->id, 'type' => 'order_rejected']
            );

            $this->smsService->sendOrderUpdate(
                $order->customer->phone,
                "[FALCO DELIVERY] لم يتم قبول طلبك من طرف المتجر. يرجى المحاولة مرة أخرى."
            );
        }

        return response()->json(['message' => 'Order rejected. Customer notified.', 'status' => 'rejected']);
    }

    public function orderHistory()
    {
        $store = auth('api')->user()->store;
        return response()->json(
            $store->orders()->where('status', 'delivered')
                ->with('items.product', 'customer')
                ->latest()->paginate(20)
        );
    }

    /**
     * PUT /api/store/orders/{id}/note
     * Store owner adds/updates store_note (visible to courier).
     * Allowed while order is between preparing and ready.
     */
    public function updateOrderNote(Request $request, $id)
    {
        $request->validate(['note' => 'required|string|max:500']);

        $store = auth('api')->user()->store;
        $order = Order::where('store_id', $store->id)->findOrFail($id);

        if (!in_array($order->status, ['courier_assigned', 'preparing', 'ready'])) {
            return response()->json(['error' => 'Store note can only be set while the order is being prepared.'], 400);
        }

        $order->update(['store_note' => $request->note]);

        return response()->json(['message' => 'Store note saved.', 'store_note' => $request->note]);
    }

    // --- Store Analytics (PRD §7.3) ---

    public function revenueAnalytics()
    {
        $store = auth('api')->user()->store;
        $data = $store->orders()
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, SUM(total_price) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json($data);
    }

    public function topProducts()
    {
        $store = auth('api')->user()->store;
        $data = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.store_id', $store->id)
            ->where('orders.status', 'delivered')
            ->selectRaw('products.name, SUM(order_items.quantity) as total_sold')
            ->groupBy('products.name')
            ->orderByDesc('total_sold')
            ->limit(10)
            ->get();

        return response()->json($data);
    }

    public function volumeAnalytics(Request $request)
    {
        $store = auth('api')->user()->store;
        $period = $request->get('period', 'day'); // day, week, month

        $format = match($period) {
            'week'  => "TO_CHAR(created_at, 'IYYY-IW')",
            'month' => "TO_CHAR(created_at, 'YYYY-MM')",
            default => 'DATE(created_at)',
        };

        $data = $store->orders()
            ->where('status', 'delivered')
            ->selectRaw("{$format} as period, COUNT(*) as total_orders")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return response()->json($data);
    }
}
