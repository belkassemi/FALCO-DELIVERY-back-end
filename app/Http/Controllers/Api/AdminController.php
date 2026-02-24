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
            'message' => 'User status updated to ' . $user->status,
            'status' => $user->status
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

        return response()->json(['message' => 'Menu change approved successfully.']);
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

    public function analytics()
    {
        return response()->json([
            'total_users'       => User::count(),
            'total_restaurants' => Restaurant::count(),
            'total_orders'      => Order::count(),
            'total_revenue'     => Order::where('status', 'delivered')->sum('total_price'),
        ]);
    }
}
