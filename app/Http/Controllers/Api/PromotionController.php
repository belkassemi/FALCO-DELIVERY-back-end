<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function index()
    {
        return response()->json(Promotion::where('is_active', true)->get());
    }

    public function apply(Request $request)
    {
        $request->validate(['code' => 'required|string']);
        
        $promotion = Promotion::where('code', $request->code)->first();

        if (!$promotion || !$promotion->isValid()) {
            return response()->json(['message' => 'Invalid or expired coupon'], 400);
        }

        return response()->json([
            'message'        => 'Coupon applied',
            'discount_type'  => $promotion->discount_type,
            'discount_value' => $promotion->discount_value
        ]);
    }
}
