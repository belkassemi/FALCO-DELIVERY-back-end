<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Category;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    /**
     * List all approved stores, optionally filtered by category.
     */
    public function index(Request $request)
    {
        $query = Store::withCoordinates()->where('is_approved', true);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('rating')) {
            $query->where('rating', '>=', $request->rating);
        }

        return response()->json($query->paginate(15));
    }

    /**
     * Find nearby stores using PostGIS.
     */
    public function nearby(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $stores = Store::withCoordinates()
            ->where('is_approved', true)
            ->nearby($request->lat, $request->lng, 10)
            ->get();

        return response()->json($stores);
    }

    /**
     * Show a single store with its products and current open status.
     */
    public function show($id)
    {
        $store = Store::withCoordinates()
            ->with(['products' => fn($q) => $q->where('is_available', true), 'hours', 'categoryRelation'])
            ->findOrFail($id);

        return response()->json(array_merge($store->toArray(), [
            'is_currently_open' => $store->isCurrentlyOpen(),
        ]));
    }

    /**
     * Get reviews for a store.
     */
    public function reviews($id)
    {
        $store = Store::findOrFail($id);
        return response()->json($store->reviews()->with('customer')->get());
    }

    /**
     * List all available categories.
     */
    public function categories()
    {
        return response()->json(Category::where('is_active', true)->orderBy('sort_order')->get());
    }
}
