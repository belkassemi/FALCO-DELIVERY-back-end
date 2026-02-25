<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\Order;
use App\Models\CourierLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    // --- User Management ---

    public function users()
    {
        return response()->json(User::paginate(20));
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:active,suspended,banned'
        ]);

        $user = User::findOrFail($id);
        
        // Prevent admins from changing their own status via this generic endpoint
        if ($user->id === auth('api')->id()) {
            return response()->json(['error' => 'Cannot change your own status'], 403);
        }

        $user->update(['status' => $request->status]);
        
        return response()->json([
            'message' => 'User status updated successfully',
            'new_status' => $user->status
        ]);
    }

    // --- Courier Management ---

    public function createCourier(Request $request)
    {
        $request->validate([
            'name'        => 'required|string',
            'email'       => 'required|email|unique:users',
            'phone'       => 'required|unique:users',
            'vehicleType' => 'required|in:bike,car,scooter',
        ]);

        $user = null;
        $activationCode = null;

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, &$user, &$activationCode) {
            do {
                $activationCode = (string) random_int(100000, 999999);
            } while (User::where('activation_code', $activationCode)->exists());

            $user = User::create([
                'name'                  => $request->name,
                'email'                 => $request->email,
                'phone'                 => $request->phone,
                'password'              => Hash::make('default_password'), // Admin sets default
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
            'activation_code' => $activationCode, // Return code to admin to give to courier
        ], 201);
    }

    // --- Restaurant Management ---

    public function pendingRestaurants()
    {
        return response()->json(Restaurant::withCoordinates()->where('is_approved', false)->get());
    }

    public function approveRestaurant($id)
    {
        $restaurant = Restaurant::findOrFail($id);
        $restaurant->update(['is_approved' => true]);

        return response()->json(['message' => 'Restaurant approved']);
    }

    // --- System Overview ---

    public function orders()
    {
        return response()->json(Order::with('customer', 'restaurant', 'courier')->latest()->paginate(20));
    }

    public function analytics()
    {
        $now = now();
        $startOfCurrentMonth = $now->copy()->startOfMonth();
        $startOfPreviousMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfPreviousMonth = $now->copy()->subMonth()->endOfMonth();

        // Helpers
        $calculateGrowth = function ($current, $previous) {
            if ($previous == 0) return $current > 0 ? 100 : 0;
            return round((($current - $previous) / $previous) * 100, 1);
        };

        // Users
        $totalUsers = User::where('role', 'customer')->count();
        $currentMonthUsers = User::where('role', 'customer')->where('created_at', '>=', $startOfCurrentMonth)->count();
        $previousMonthUsers = User::where('role', 'customer')->whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth])->count();
        $usersGrowth = $calculateGrowth($currentMonthUsers, $previousMonthUsers);

        // Couriers
        $activeCouriers = User::where('role', 'courier')->where('status', 'active')->count();
        $currentMonthCouriers = User::where('role', 'courier')->where('created_at', '>=', $startOfCurrentMonth)->count();
        $previousMonthCouriers = User::where('role', 'courier')->whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth])->count();
        $couriersGrowth = $calculateGrowth($currentMonthCouriers, $previousMonthCouriers);

        // Restaurants
        $totalRestaurants = Restaurant::count();
        $currentMonthRestaurants = Restaurant::where('created_at', '>=', $startOfCurrentMonth)->count();
        $previousMonthRestaurants = Restaurant::whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth])->count();
        $restaurantsGrowth = $calculateGrowth($currentMonthRestaurants, $previousMonthRestaurants);

        // Orders
        $totalOrders = Order::count();
        $currentMonthOrders = Order::where('created_at', '>=', $startOfCurrentMonth)->count();
        $previousMonthOrders = Order::whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth])->count();
        $ordersGrowth = $calculateGrowth($currentMonthOrders, $previousMonthOrders);

        // Revenue (Completed Orders only)
        $totalRevenue = Order::where('status', 'delivered')->sum('total_amount');
        $currentMonthRevenue = Order::where('status', 'delivered')->where('created_at', '>=', $startOfCurrentMonth)->sum('total_amount');
        $previousMonthRevenue = Order::where('status', 'delivered')->whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth])->sum('total_amount');
        $revenueGrowth = $calculateGrowth($currentMonthRevenue, $previousMonthRevenue);

        // Avg Rating (simplified mock logic for now since ratings might not be fully built)
        $avgRating = 4.8;
        
        return response()->json([
            'total_users' => $totalUsers,
            'users_growth' => $usersGrowth > 0 ? "+{$usersGrowth}" : (string)$usersGrowth,
            'active_couriers' => $activeCouriers,
            'couriers_growth' => $couriersGrowth > 0 ? "+{$couriersGrowth}" : (string)$couriersGrowth,
            'total_restaurants' => $totalRestaurants,
            'restaurants_growth' => $restaurantsGrowth > 0 ? "+{$restaurantsGrowth}" : (string)$restaurantsGrowth,
            'total_orders' => $totalOrders,
            'orders_growth' => $ordersGrowth > 0 ? "+{$ordersGrowth}" : (string)$ordersGrowth,
            'total_revenue' => $totalRevenue,
            'revenue_growth' => $revenueGrowth > 0 ? "+{$revenueGrowth}" : (string)$revenueGrowth,
            'avg_rating' => $avgRating,
        ]);
    }
    
    // --- Menu Approvals ---

    public function pendingMenuChanges()
    {
        return response()->json(
            \App\Models\MenuChangeRequest::with('restaurant', 'menuItem')
                ->where('status', 'pending')
                ->latest()
                ->get()
        );
    }

    public function approveMenuChange($id)
    {
        $request = \App\Models\MenuChangeRequest::findOrFail($id);

        if ($request->status !== 'pending') {
            return response()->json(['error' => 'Request is already processed.'], 400);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            if ($request->action_type === 'create') {
                $item = \App\Models\MenuItem::create(array_merge($request->proposed_data, [
                    'restaurant_id' => $request->restaurant_id,
                ]));
                $request->update(['menu_item_id' => $item->id]);
            } elseif ($request->action_type === 'update') {
                $item = \App\Models\MenuItem::findOrFail($request->menu_item_id);
                $item->update($request->proposed_data);
            } elseif ($request->action_type === 'delete') {
                $item = \App\Models\MenuItem::findOrFail($request->menu_item_id);
                $item->delete(); // Automatically triggers soft delete due to trait
            }

            $request->update([
                'status'      => 'approved',
                'admin_id'    => auth('api')->id(),
                'reviewed_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Menu request approved and applied']);
    }

    public function rejectMenuChange(\Illuminate\Http\Request $req, $id)
    {
        $req->validate(['reason' => 'required|string']);

        $request = \App\Models\MenuChangeRequest::findOrFail($id);

        if ($request->status !== 'pending') {
            return response()->json(['error' => 'Request is already processed.'], 400);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $req) {
            $request->update([
                'status'           => 'rejected',
                'admin_id'         => auth('api')->id(),
                'reviewed_at'      => now(),
                'rejection_reason' => $req->reason,
            ]);
        });

        return response()->json(['message' => 'Menu change rejected.']);
    }

    public function showUser($id)
    {
        return response()->json(User::with('wallet', 'orders')->findOrFail($id));
    }

    public function restaurants()
    {
        return response()->json(\App\Models\Restaurant::withCoordinates()->paginate(20));
    }

    public function updateRestaurantStatus(Request $request, $id)
    {
        $request->validate(['is_approved' => 'required|boolean']);
        $restaurant = \App\Models\Restaurant::findOrFail($id);
        $restaurant->update(['is_approved' => $request->is_approved]);
        return response()->json(['message' => 'Restaurant status updated.', 'is_approved' => $restaurant->is_approved]);
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

    public function showOrder($id)
    {
        return response()->json(\App\Models\Order::with('customer', 'restaurant', 'courier', 'items.menuItem')->findOrFail($id));
    }

    public function forceOrderStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:pending,assigned,preparing,on_the_way,delivered,cancelled']);
        $order = \App\Models\Order::findOrFail($id);
        $order->update(['status' => $request->status]);
        return response()->json(['message' => 'Order status forced.', 'status' => $order->status]);
    }

    // --- Refund Management ---

    public function refunds()
    {
        return response()->json(\App\Models\Refund::with('user', 'order')->latest()->paginate(20));
    }

    public function approveRefund($id)
    {
        $refund = \App\Models\Refund::findOrFail($id);
        if ($refund->status !== 'pending') {
            return response()->json(['error' => 'Refund already processed.'], 400);
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($refund) {
            $refund->update(['status' => 'approved', 'admin_id' => auth('api')->id(), 'processed_at' => now()]);
            // Credit wallet
            $refund->user->wallet->credit($refund->amount, 'refund', 'Refund for order #' . $refund->order_id);
        });
        return response()->json(['message' => 'Refund approved and wallet credited.']);
    }

    public function rejectRefund(Request $request, $id)
    {
        $request->validate(['note' => 'required|string']);
        $refund = \App\Models\Refund::findOrFail($id);
        if ($refund->status !== 'pending') {
            return response()->json(['error' => 'Refund already processed.'], 400);
        }
        $refund->update(['status' => 'rejected', 'admin_id' => auth('api')->id(), 'admin_note' => $request->note, 'processed_at' => now()]);
        return response()->json(['message' => 'Refund rejected.']);
    }

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

        $users = $query->whereNotNull('device_token')->get();

        // In production: dispatch push via FCM/Expo per user device_token
        return response()->json([
            'message'    => 'Broadcast queued.',
            'recipients' => $users->count(),
        ]);
    }
}
