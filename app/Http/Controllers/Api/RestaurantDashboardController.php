<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RestaurantDashboardController extends Controller
{
    /**
     * Get the authenticated user's restaurant with its menu and analytics.
     */
    public function index()
    {
        // TECHNICAL CONSTRAINT: Use withCoordinates for spatial extraction
        $restaurant = auth('api')->user()->restaurant()
            ->withCoordinates()
            ->with('menuItems')
            ->first();
            
        if (!$restaurant) {
            return response()->json(['message' => 'Restaurant not found'], 404);
        }
        return response()->json($restaurant);
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'name'        => 'required|string',
            'category'    => 'nullable|string',
            'address'     => 'nullable|string',
            'lat'         => 'required|numeric',
            'lng'         => 'required|numeric',
            'phone'       => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $restaurant = auth('api')->user()->restaurant;

        // Update basic info
        $restaurant->update($request->only(['name', 'category', 'address', 'phone', 'description']));

        // TECHNICAL CONSTRAINT: Use ST_SetSRID and ST_MakePoint for geography POINT
        \Illuminate\Support\Facades\DB::statement(
            "UPDATE restaurants SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, updated_at = NOW() WHERE id = ?",
            [$request->lng, $request->lat, $restaurant->id]
        );

        return response()->json(['message' => 'Profile updated']);
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:5120', // allow up to 5MB
        ]);

        $restaurant = auth('api')->user()->restaurant;

        if ($request->hasFile('image')) {
            // Delete old image if it exists
            if ($restaurant->image) {
                Storage::disk('public')->delete($restaurant->image);
            }
            
            // Store new image in 'restaurants' folder
            $path = $request->file('image')->store('restaurants', 'public');
            
            // Generate full URL
            $fullUrl = url('storage/' . $path);
            $restaurant->update(['image' => $fullUrl]);

            return response()->json([
                'message'   => 'Restaurant image uploaded successfully',
                'image_url' => $fullUrl
            ]);
        }

        return response()->json(['error' => 'No image provided'], 400);
    }

    public function updateStatus(Request $request)
    {
        $request->validate(['is_open' => 'required|boolean']);
        $owner      = auth('api')->user();
        $restaurant = $owner->restaurant;

        // Block opening if account is suspended or banned
        if ($request->is_open && $owner->status !== 'active') {
            return response()->json([
                'error' => 'Cannot open restaurant while account is ' . $owner->status . '.'
            ], 403);
        }

        $restaurant->update(['is_open' => $request->is_open]);

        return response()->json(['message' => 'Status updated', 'is_open' => $restaurant->is_open]);
    }

    // --- Menu Management ---

    public function addMenuItem(Request $request)
    {
        // TECHNICAL CONSTRAINT: Mandatory PNG and specific dimensions
        $request->validate([
            'name'        => 'required|string',
            'description' => 'nullable|string',
            'price'       => 'required|numeric',
            'category'    => 'nullable|string',
            'image'       => 'required|image|mimes:png|dimensions:min_width=100,min_height=100,max_width=2000,max_height=2000',
        ]);

        $restaurant = auth('api')->user()->restaurant;
        
        $data = $request->except('image');
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('menu', 'public');
        }

        // Create Change Request instead of live item
        \App\Models\MenuChangeRequest::create([
            'restaurant_id' => $restaurant->id,
            'menu_item_id'  => null,
            'action_type'   => 'create',
            'proposed_data' => $data,
            'old_data'      => null,
            'requested_by'  => auth('api')->id(),
        ]);

        return response()->json([
            'message' => 'Menu change request submitted',
            'request_status' => 'pending_admin_approval'
        ], 201);
    }

    public function updateMenuItem(Request $request, $id)
    {
        $item = MenuItem::findOrFail($id);
        $this->authorize('manage', $item);

        // Filter only what actually changed or what is being requested
        $proposedData = $request->all();

        \App\Models\MenuChangeRequest::create([
            'restaurant_id' => $item->restaurant_id,
            'menu_item_id'  => $item->id,
            'action_type'   => 'update',
            'proposed_data' => $proposedData,
            'old_data'      => $item->toArray(),
            'requested_by'  => auth('api')->id(),
        ]);

        return response()->json(['message' => 'Menu item update request submitted for admin approval.', 'item' => $item]);
    }

    public function deleteMenuItem($id)
    {
        $item = MenuItem::findOrFail($id);
        $this->authorize('manage', $item);
        
        \App\Models\MenuChangeRequest::create([
            'restaurant_id' => $item->restaurant_id,
            'menu_item_id'  => $item->id,
            'action_type'   => 'delete',
            'proposed_data' => null,
            'old_data'      => $item->toArray(),
            'requested_by'  => auth('api')->id(),
        ]);

        return response()->json(['message' => 'Menu item deletion request submitted for admin approval.']);
    }

    // --- Order Management ---

    public function getOrders()
    {
        $restaurant = auth('api')->user()->restaurant;
        return response()->json($restaurant->orders()->with('items.menuItem', 'customer')->latest()->get());
    }

    public function acceptOrder($id)
    {
        $order = Order::findOrFail($id);
        $this->authorize('restaurantAction', $order);

        $order->update(['status' => 'preparing']);

        return response()->json(['message' => 'Order accepted', 'status' => 'preparing']);
    }

    public function orderReady($id)
    {
        $order = Order::findOrFail($id);
        $this->authorize('restaurantAction', $order);

        $order->update(['status' => 'on_the_way']);

        return response()->json(['message' => 'Order marked as ready', 'status' => 'on_the_way']);
    }

    public function getMenu()
    {
        $restaurant = auth('api')->user()->restaurant;
        return response()->json($restaurant->menuItems()->get());
    }

    public function orderHistory()
    {
        $restaurant = auth('api')->user()->restaurant;
        return response()->json(
            $restaurant->orders()
                ->where('status', 'delivered')
                ->with('items.menuItem', 'customer')
                ->latest()
                ->paginate(20)
        );
    }

    public function rejectOrder($id)
    {
        $order = Order::findOrFail($id);
        $this->authorize('restaurantAction', $order);

        $order->update(['status' => 'cancelled']);
        return response()->json(['message' => 'Order rejected by restaurant.', 'status' => 'cancelled']);
    }
}
