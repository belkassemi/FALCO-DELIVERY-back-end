<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    /**
     * List all approved restaurants with filters.
     */
    public function index(Request $request)
    {
        $query = Restaurant::withCoordinates()->where('is_approved', true);

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('rating')) {
            $query->where('rating', '>=', $request->rating);
        }

        return response()->json($query->paginate(15));
    }

    public function nearby(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $lat = $request->lat;
        $lng = $request->lng;
        $radius = 10; // Default 10km

        // TECHNICAL CONSTRAINT: Use native PostGIS nearby scope
        $restaurants = Restaurant::withCoordinates()
            ->where('is_approved', true)
            ->where('is_open', true)
            ->nearby($lat, $lng, $radius)
            ->get();

        return response()->json($restaurants);
    }

    public function show($id)
    {
        $restaurant = Restaurant::withCoordinates()->with(['menuItems' => function ($q) {
            $q->where('is_available', true);
        }])->findOrFail($id);

        return response()->json($restaurant);
    }

    public function reviews($id)
    {
        $restaurant = Restaurant::findOrFail($id);
        return response()->json($restaurant->reviews()->with('customer')->get());
    }
}
