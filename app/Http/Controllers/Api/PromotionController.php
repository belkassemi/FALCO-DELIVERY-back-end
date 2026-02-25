<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromotionController extends Controller
{
    public function index()
    {
        return response()->json(Promotion::where('is_active', true)->get());
    }

    /**
     * POST /api/promotions/apply
     * Validates: expiry, min_order_amount, usage_limit, per_user_limit.
     */
    public function apply(Request $request)
    {
        $request->validate([
            'code'     => 'required|string',
            'order_id' => 'required|exists:orders,id',
        ]);

        $promotion = Promotion::where('code', $request->code)->first();

        // Existence + active check
        if (!$promotion || !$promotion->is_active) {
            return response()->json(['error' => 'Invalid or inactive coupon.'], 400);
        }

        // Expiry check
        if ($promotion->expires_at && now()->isAfter($promotion->expires_at)) {
            return response()->json(['error' => 'This coupon has expired.'], 400);
        }

        // Min order amount check
        $order = \App\Models\Order::findOrFail($request->order_id);
        if (!empty($promotion->min_order_amount) && $order->total_price < $promotion->min_order_amount) {
            return response()->json([
                'error' => 'Order total does not meet the minimum amount of ' . $promotion->min_order_amount . ' for this coupon.'
            ], 400);
        }

        // Global usage limit check
        if (!empty($promotion->usage_limit) && $promotion->times_used >= $promotion->usage_limit) {
            return response()->json(['error' => 'This coupon has reached its usage limit.'], 400);
        }

        // Per-user usage limit check
        if (!empty($promotion->per_user_limit)) {
            $userUsages = DB::table('promotion_usages')
                ->where('promotion_id', $promotion->id)
                ->where('user_id', auth('api')->id())
                ->count();

            if ($userUsages >= $promotion->per_user_limit) {
                return response()->json(['error' => 'You have already used this coupon the maximum number of times.'], 400);
            }
        }

        // Record usage
        DB::table('promotion_usages')->insert([
            'promotion_id' => $promotion->id,
            'user_id'      => auth('api')->id(),
            'order_id'     => $order->id,
            'used_at'      => now(),
        ]);

        $promotion->increment('times_used');

        return response()->json([
            'message'        => 'Coupon applied successfully',
            'discount_type'  => $promotion->discount_type,
            'discount_value' => $promotion->discount_value,
        ]);
    }
}
