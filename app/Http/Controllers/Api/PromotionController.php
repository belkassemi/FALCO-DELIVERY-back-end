<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\Order;
use App\Models\Store;
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
     * Validates: expiry, min_order, usage_limit, category eligibility.
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

        // Min order amount check (column is `min_order` in DB)
        $order = Order::findOrFail($request->order_id);
        if ($promotion->min_order > 0 && $order->total_price < $promotion->min_order) {
            return response()->json([
                'error' => 'Order total does not meet the minimum amount of ' . $promotion->min_order . ' for this coupon.'
            ], 400);
        }

        // PRD §10.3: Store category eligibility check
        if (!empty($promotion->eligible_categories)) {
            $store = Store::find($order->store_id);
            if ($store && !in_array($store->category_id, $promotion->eligible_categories)) {
                return response()->json([
                    'error' => 'This coupon is not valid for this store category.'
                ], 400);
            }
        }

        // Global usage limit check (column is `used_count` in DB)
        if ($promotion->usage_limit && $promotion->used_count >= $promotion->usage_limit) {
            return response()->json(['error' => 'This coupon has reached its usage limit.'], 400);
        }

        // Per-user check: has this user already used this promo? (promotion_users table)
        $alreadyUsed = DB::table('promotion_users')
            ->where('promotion_id', $promotion->id)
            ->where('user_id', auth('api')->id())
            ->exists();

        if ($alreadyUsed) {
            return response()->json(['error' => 'You have already used this coupon.'], 400);
        }

        // Record usage
        DB::table('promotion_users')->insert([
            'promotion_id' => $promotion->id,
            'user_id'      => auth('api')->id(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $promotion->increment('used_count');

        return response()->json([
            'message'        => 'Coupon applied successfully',
            'discount_type'  => $promotion->discount_type,
            'discount_value' => $promotion->discount_value,
        ]);
    }
}
