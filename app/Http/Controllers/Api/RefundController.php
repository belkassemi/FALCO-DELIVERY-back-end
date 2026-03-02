<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Http\Request;

/**
 * PRD §10.2 — Refund Endpoints
 *
 * Customer can submit refund requests only after an order is delivered.
 * Admin reviews and resolves them offline (cash or bank transfer).
 * No automated money movement occurs.
 */
class RefundController extends Controller
{
    /**
     * GET /api/refunds
     * List all refund requests submitted by the authenticated customer.
     */
    public function index()
    {
        return response()->json(
            Refund::where('user_id', auth('api')->id())->latest()->get()
        );
    }

    /**
     * POST /api/refunds
     * Customer submits a refund request.
     *
     * Validations:
     * - order must belong to authenticated customer
     * - order status must be 'delivered'
     * - no duplicate pending refund for the same order
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'reason'   => 'required|string|max:1000',
        ]);

        $order = Order::findOrFail($data['order_id']);

        if ($order->customer_id !== auth('api')->id()) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        if ($order->status !== 'delivered') {
            return response()->json([
                'error' => 'Refund requests can only be submitted after the order is delivered.',
            ], 422);
        }

        if (Refund::where('order_id', $data['order_id'])->where('status', 'pending')->exists()) {
            return response()->json([
                'error' => 'A pending refund request already exists for this order.',
            ], 422);
        }

        $refund = Refund::create([
            'user_id'  => auth('api')->id(),
            'order_id' => $data['order_id'],
            'amount'   => $order->total_price,
            'reason'   => $data['reason'],
        ]);

        return response()->json([
            'message'   => 'Refund request submitted successfully.',
            'refund_id' => $refund->id,
            'status'    => 'pending',
        ], 201);
    }
}
