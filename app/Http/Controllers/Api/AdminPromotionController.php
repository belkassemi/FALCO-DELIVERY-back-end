<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\Request;

class AdminPromotionController extends Controller
{
    /**
     * POST /api/admin/promotions
     */
    public function store(Request $request)
    {
        $request->validate([
            'code'           => 'required|string|unique:promotions,code',
            'discount_type'  => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'expires_at'     => 'nullable|date|after:now',
            'is_active'      => 'boolean',
        ]);

        $promotion = Promotion::create($request->all());

        return response()->json(['message' => 'Promotion created successfully', 'promotion' => $promotion], 201);
    }

    /**
     * PUT /api/admin/promotions/{id}
     */
    public function update(Request $request, $id)
    {
        $promotion = Promotion::findOrFail($id);

        $request->validate([
            'code'           => 'sometimes|string|unique:promotions,code,' . $id,
            'discount_type'  => 'sometimes|in:percentage,fixed',
            'discount_value' => 'sometimes|numeric|min:0',
            'expires_at'     => 'nullable|date',
            'is_active'      => 'boolean',
        ]);

        $promotion->update($request->all());

        return response()->json(['message' => 'Promotion updated successfully', 'promotion' => $promotion]);
    }

    /**
     * DELETE /api/admin/promotions/{id}
     */
    public function destroy($id)
    {
        $promotion = Promotion::findOrFail($id);
        $promotion->delete();

        return response()->json(['message' => 'Promotion deleted successfully']);
    }
}
