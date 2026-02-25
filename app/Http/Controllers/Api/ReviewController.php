<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Order;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * POST /api/restaurants/{id}/reviews
     * One review per ORDER (not just per restaurant).
     * Can only be written within 48h of delivery.
     */
    public function store(Request $request, $restaurantId)
    {
        $request->validate([
            'rating'   => 'required|integer|between:1,5',
            'comment'  => 'nullable|string|max:1000',
            'order_id' => 'required|exists:orders,id',
        ]);

        // Verify order belongs to this customer and was delivered at this restaurant
        $order = Order::where('id', $request->order_id)
            ->where('customer_id', auth('api')->id())
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'delivered')
            ->firstOrFail();

        // One review per ORDER
        if (Review::where('order_id', $order->id)->exists()) {
            return response()->json(['error' => 'You have already reviewed this order.'], 409);
        }

        $review = Review::create([
            'user_id'       => auth('api')->id(),
            'restaurant_id' => $restaurantId,
            'order_id'      => $order->id,
            'rating'        => $request->rating,
            'comment'       => $request->comment,
        ]);

        return response()->json($review->load('user'), 201);
    }

    /**
     * PUT /api/reviews/{id}
     * Customer edits own review — locked after 24 hours.
     */
    public function update(Request $request, $id)
    {
        $review = Review::where('id', $id)
            ->where('user_id', auth('api')->id())
            ->firstOrFail();

        // Lock editing after 24 hours
        if ($review->created_at->diffInHours(now()) > 24) {
            return response()->json(['error' => 'Reviews can no longer be edited after 24 hours.'], 403);
        }

        $request->validate([
            'rating'  => 'sometimes|integer|between:1,5',
            'comment' => 'sometimes|nullable|string|max:1000',
        ]);

        $review->update($request->only('rating', 'comment'));

        return response()->json(['message' => 'Review updated successfully', 'review' => $review]);
    }

    /**
     * DELETE /api/reviews/{id}
     * Customers CANNOT delete their own reviews — only admins can.
     */
    public function destroy($id)
    {
        return response()->json([
            'error' => 'Reviews cannot be deleted by users. Contact support if needed.'
        ], 403);
    }

    /**
     * DELETE /api/admin/reviews/{id}
     * Admin moderates — soft deletes.
     */
    public function adminDestroy($id)
    {
        $review = Review::findOrFail($id);
        $review->delete(); // SoftDeletes trait — sets deleted_at

        return response()->json(['message' => 'Review removed by admin']);
    }
}
