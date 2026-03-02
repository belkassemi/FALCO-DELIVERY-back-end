<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Store;
use App\Models\Order;
use App\Models\Product;
use App\Models\CourierLocation;
use App\Models\CourierMonthlyStat;
use App\Models\MenuChangeRequest;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    // --- User Management ---

    public function users()
    {
        return response()->json(User::paginate(20));
    }

    public function showUser($id)
    {
        return response()->json(User::with('orders')->findOrFail($id));
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:active,suspended,banned']);

        $user = User::findOrFail($id);
        if ($user->id === auth('api')->id()) {
            return response()->json(['error' => 'Cannot change your own status'], 403);
        }

        $user->update(['status' => $request->status]);
        return response()->json(['message' => 'User status updated', 'new_status' => $user->status]);
    }

    // --- Courier Management ---

    public function createCourier(Request $request)
    {
        $request->validate([
            'name'        => 'required|string',
            'email'       => 'nullable|email|unique:users',
            'phone'       => 'required|unique:users',
            'vehicleType' => 'required|in:bike,car,scooter',
        ]);

        $user = null;
        $activationCode = null;

        DB::transaction(function () use ($request, &$user, &$activationCode) {
            do {
                $activationCode = (string) random_int(100000, 999999);
            } while (User::where('activation_code', $activationCode)->exists());

            $user = User::create([
                'name'                  => $request->name,
                'email'                 => $request->email,
                'phone'                 => $request->phone,
                'password'              => Hash::make('default_password'),
                'role'                  => 'courier',
                'activation_code'       => $activationCode,
                'activation_expires_at' => now()->addDays(7),
                'activation_attempts'   => 0,
                'is_activated'          => false,
            ]);

            CourierLocation::create([
                'courier_id'   => $user->id,
                'vehicle_type' => $request->vehicleType,
            ]);
        });

        return response()->json([
            'courierId'       => $user->id,
            'status'          => 'created',
            'activation_code' => $activationCode,
        ], 201);
    }

    public function couriers()
    {
        return response()->json(User::where('role', 'courier')->with('courierLocation')->paginate(20));
    }

    public function updateCourierStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:active,suspended,banned']);
        $courier = User::where('role', 'courier')->findOrFail($id);
        $courier->update(['status' => $request->status]);
        return response()->json(['message' => 'Courier status updated.', 'status' => $courier->status]);
    }

    /**
     * Admin monthly stats for couriers (PRD §8.4).
     */
    public function courierMonthlyStats($courierId)
    {
        $stats = CourierMonthlyStat::where('courier_id', $courierId)
            ->orderByDesc('month')
            ->paginate(12);

        return response()->json($stats);
    }

    // --- Store Management ---

    public function stores()
    {
        return response()->json(Store::withCoordinates()->with('owner', 'categoryRelation')->get());
    }

    /**
     * PRD §9.2: Admin creates store owner account.
     * No self-registration exists for store owners.
     */
    public function createStoreOwner(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|min:6',
            'phone'    => 'required|string|unique:users',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'phone'    => $request->phone,
            'password' => Hash::make($request->password),
            'role'     => 'restaurant_owner',
            'status'   => 'active',
        ]);

        return response()->json([
            'message' => 'Store owner account created. Share credentials out-of-band.',
            'user_id' => $user->id,
            'email'   => $user->email,
        ], 201);
    }

    /**
     * PRD §9.2: Admin creates the store and assigns to an existing owner.
     * Stores are immediately live — no approval state.
     */
    public function createStore(Request $request)
    {
        $request->validate([
            'owner_id'    => 'required|exists:users,id',
            'name'        => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'address'     => 'nullable|string|max:255',
            'lat'         => 'required|numeric',
            'lng'         => 'required|numeric',
            'phone'       => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $owner = User::where('role', 'restaurant_owner')->findOrFail($request->owner_id);

        $store = null;
        DB::transaction(function () use ($request, $owner, &$store) {
            $store = Store::create([
                'user_id'     => $owner->id,
                'category_id' => $request->category_id,
                'name'        => $request->name,
                'address'     => $request->address,
                'phone'       => $request->phone,
                'description' => $request->description,
            ]);

            // PRD §9.2: Stores are immediately live — set via raw DB since not in fillable
            DB::statement(
                "UPDATE stores SET is_approved = true, is_open = false, location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, updated_at = NOW() WHERE id = ?",
                [$request->lng, $request->lat, $store->id]
            );
        });

        return response()->json([
            'message'  => 'Store created and is immediately live.',
            'store_id' => $store->id,
        ], 201);
    }

    public function updateStoreStatus(Request $request, $id)
    {
        $request->validate(['is_approved' => 'required|boolean']);
        $store = Store::findOrFail($id);

        // Use raw DB since is_approved is not in Store::$fillable
        DB::table('stores')->where('id', $id)->update([
            'is_approved' => $request->is_approved,
            'updated_at'  => now(),
        ]);

        return response()->json(['message' => 'Store status updated.', 'is_approved' => $request->is_approved]);
    }

    // --- Orders ---

    public function orders()
    {
        return response()->json(Order::with('customer', 'store', 'courier')->latest()->paginate(20));
    }

    public function showOrder($id)
    {
        return response()->json(Order::with('customer', 'store', 'courier', 'items.product')->findOrFail($id));
    }

    public function forceOrderStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,store_notified,store_confirmed,courier_searching,courier_assigned,preparing,ready,picked_up,delivered,cancelled,rejected,no_courier_found',
        ]);

        $order = Order::findOrFail($id);
        $order->update(['status' => $request->status]);
        return response()->json(['message' => 'Order status forced.', 'status' => $order->status]);
    }

    // --- Menu Change Approvals ---

    public function pendingMenuChanges()
    {
        return response()->json(
            MenuChangeRequest::with('store', 'product')
                ->where('status', 'pending')
                ->latest()
                ->get()
        );
    }

    public function approveMenuChange($id)
    {
        $request = MenuChangeRequest::findOrFail($id);

        if ($request->status !== 'pending') {
            return response()->json(['error' => 'Request is already processed.'], 400);
        }

        DB::transaction(function () use ($request) {
            if ($request->action_type === 'create') {
                $item = Product::create(array_merge($request->proposed_data, [
                    'store_id' => $request->store_id,
                ]));
                $request->update(['product_id' => $item->id]);
            } elseif ($request->action_type === 'update') {
                $item = Product::findOrFail($request->product_id);
                $item->update($request->proposed_data);
            } elseif ($request->action_type === 'delete') {
                $item = Product::findOrFail($request->product_id);
                $item->delete();
            }

            $request->update([
                'status'      => 'approved',
                'admin_id'    => auth('api')->id(),
                'reviewed_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Menu request approved and applied']);
    }

    public function rejectMenuChange(Request $req, $id)
    {
        $req->validate(['reason' => 'required|string']);

        $request = MenuChangeRequest::findOrFail($id);
        if ($request->status !== 'pending') {
            return response()->json(['error' => 'Request is already processed.'], 400);
        }

        $request->update([
            'status'           => 'rejected',
            'admin_id'         => auth('api')->id(),
            'reviewed_at'      => now(),
            'rejection_reason' => $req->reason,
        ]);

        return response()->json(['message' => 'Menu change rejected.']);
    }

    // --- Refund Management ---

    public function refunds()
    {
        return response()->json(\App\Models\Refund::with('user', 'order')->latest()->paginate(20));
    }

    public function approveRefund(Request $request, $id)
    {
        $request->validate([
            'refund_method' => 'required|in:cash,bank_transfer',
            'admin_note'    => 'nullable|string',
        ]);

        $refund = \App\Models\Refund::findOrFail($id);
        if ($refund->status !== 'pending') {
            return response()->json(['error' => 'Refund already processed.'], 400);
        }

        // PRD §10.2: No automated money movement. Admin handles offline.
        $refund->update([
            'status'        => 'approved',
            'refund_method' => $request->refund_method,
            'admin_id'      => auth('api')->id(),
            'admin_note'    => $request->admin_note,
            'processed_at'  => now(),
            'resolved_at'   => now(),
        ]);

        return response()->json(['message' => 'Refund approved. Process the ' . $request->refund_method . ' payment offline.']);
    }

    public function rejectRefund(Request $request, $id)
    {
        $request->validate(['admin_note' => 'required|string']);

        $refund = \App\Models\Refund::findOrFail($id);
        if ($refund->status !== 'pending') {
            return response()->json(['error' => 'Refund already processed.'], 400);
        }

        $refund->update([
            'status'       => 'rejected',
            'admin_id'     => auth('api')->id(),
            'admin_note'   => $request->admin_note,
            'processed_at' => now(),
            'resolved_at'  => now(),
        ]);

        return response()->json(['message' => 'Refund rejected.']);
    }

    // --- Settings Management ---

    public function settings()
    {
        return response()->json(Setting::all());
    }

    public function updateSetting(Request $request)
    {
        $request->validate([
            'key'   => 'required|string',
            'value' => 'required|string',
        ]);

        Setting::updateOrCreate(
            ['key' => $request->key],
            ['value' => $request->value]
        );

        return response()->json(['message' => 'Setting updated.']);
    }

    // --- Categories Management ---

    public function categoriesList()
    {
        return response()->json(\App\Models\Category::orderBy('sort_order')->get());
    }

    public function createCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'slug' => 'required|string|unique:categories',
            'icon' => 'nullable|string',
        ]);

        $cat = \App\Models\Category::create($request->all());
        return response()->json($cat, 201);
    }

    public function updateCategory(Request $request, $id)
    {
        $cat = \App\Models\Category::findOrFail($id);
        $cat->update($request->all());
        return response()->json(['message' => 'Category updated.']);
    }

    // --- Analytics ---

    public function analytics()
    {
        $now = now();
        $startOfCurrentMonth = $now->copy()->startOfMonth();
        $startOfPreviousMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfPreviousMonth = $now->copy()->subMonth()->endOfMonth();

        $calculateGrowth = function ($current, $previous) {
            if ($previous == 0) return $current > 0 ? 100 : 0;
            return round((($current - $previous) / $previous) * 100, 1);
        };

        $totalUsers = User::where('role', 'customer')->count();
        $currentMonthUsers = User::where('role', 'customer')->where('created_at', '>=', $startOfCurrentMonth)->count();
        $previousMonthUsers = User::where('role', 'customer')->whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth])->count();
        $usersGrowth = $calculateGrowth($currentMonthUsers, $previousMonthUsers);

        $activeCouriers = User::where('role', 'courier')->where('status', 'active')->count();
        $totalStores = Store::count();
        $totalOrders = Order::count();
        $totalRevenue = Order::where('status', 'delivered')->sum('total_price');

        return response()->json([
            'total_users'       => $totalUsers,
            'users_growth'      => $usersGrowth > 0 ? "+{$usersGrowth}" : (string)$usersGrowth,
            'active_couriers'   => $activeCouriers,
            'total_stores'      => $totalStores,
            'total_orders'      => $totalOrders,
            'total_revenue'     => $totalRevenue,
        ]);
    }

    // --- Broadcast ---

    public function broadcastNotification(Request $request)
    {
        $request->validate([
            'title'   => 'required|string',
            'message' => 'required|string',
            'role'    => 'nullable|in:customer,courier,restaurant_owner,all',
        ]);

        $query = User::query();
        if ($request->role && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        // PRD §4.8: Query device_tokens table instead of users.device_token
        $userIds = $query->pluck('id');
        $tokens = \App\Models\DeviceToken::whereIn('user_id', $userIds)->get();

        // TODO: Send FCM push to each token

        return response()->json([
            'message'    => 'Broadcast queued.',
            'recipients' => $tokens->groupBy('user_id')->count(),
        ]);
    }
}
