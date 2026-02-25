<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * POST /api/payment/checkout
     * Guards: order must be pending, no double payment, links payment_status to order.
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'order_id'       => 'required|exists:orders,id',
            'payment_method' => 'required|in:card,wallet,cash',
        ]);

        $order = Order::findOrFail($request->order_id);

        if ($order->customer_id !== auth('api')->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Guard 1: order must be pending
        if ($order->status !== 'pending') {
            return response()->json([
                'error' => 'Payment can only be initiated for pending orders.'
            ], 400);
        }

        // Guard 2: prevent double payment
        $existingPayment = Payment::where('order_id', $order->id)
            ->where('status', 'completed')
            ->first();

        if ($existingPayment) {
            return response()->json([
                'error'      => 'This order has already been paid.',
                'payment_id' => $existingPayment->id,
            ], 409);
        }

        return DB::transaction(function () use ($request, $order) {
            // If paying by wallet, deduct immediately
            if ($request->payment_method === 'wallet') {
                $wallet = auth('api')->user()->wallet;
                if ($wallet->balance < $order->total_price) {
                    return response()->json(['error' => 'Insufficient wallet balance.'], 400);
                }
                $wallet->debit($order->total_price, 'order_payment', 'Payment for order #' . $order->id);
            }

            $paymentStatus = in_array($request->payment_method, ['wallet', 'card']) ? 'completed' : 'pending';

            $payment = Payment::create([
                'order_id' => $order->id,
                'user_id'  => auth('api')->id(),
                'amount'   => $order->total_price,
                'method'   => $request->payment_method,
                'status'   => $paymentStatus,
            ]);

            // Directly link payment_status on order
            if ($paymentStatus === 'completed') {
                $order->update(['payment_status' => 'paid']);
            }

            return response()->json([
                'message'        => 'Payment initiated successfully',
                'payment_id'     => $payment->id,
                'payment_status' => $payment->status,
                'order_payment_status' => $order->fresh()->payment_status,
            ], 201);
        });
    }

    /**
     * POST /api/payment/webhook  (Public — provider callback)
     */
    public function webhook(Request $request)
    {
        $orderId = $request->input('order_id');
        $status  = $request->input('status');

        $payment = Payment::where('order_id', $orderId)->latest()->first();
        if ($payment) {
            $isPaid = $status === 'paid';
            $payment->update(['status' => $isPaid ? 'completed' : 'failed']);
            if ($isPaid) {
                Order::where('id', $orderId)->update(['payment_status' => 'paid']);
            }
        }

        return response()->json(['received' => true]);
    }

    /**
     * GET /api/payment/history
     */
    public function history()
    {
        return response()->json(
            Payment::where('user_id', auth('api')->id())->with('order')->latest()->get()
        );
    }

    /**
     * POST /api/payment/refund-request
     */
    public function refundRequest(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'reason'   => 'required|string|max:500',
        ]);

        $order = Order::findOrFail($request->order_id);

        if ($order->customer_id !== auth('api')->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($order->status !== 'delivered') {
            return response()->json(['error' => 'Refunds can only be requested on delivered orders.'], 400);
        }

        if (Refund::where('order_id', $order->id)->where('user_id', auth('api')->id())->exists()) {
            return response()->json(['error' => 'A refund request already exists for this order.'], 409);
        }

        $refund = Refund::create([
            'user_id'  => auth('api')->id(),
            'order_id' => $order->id,
            'amount'   => $order->total_price,
            'reason'   => $request->reason,
        ]);

        return response()->json([
            'message'   => 'Refund request submitted successfully',
            'refund_id' => $refund->id,
            'status'    => 'pending',
        ], 201);
    }

    /**
     * GET /api/payment/refund-status/{id}
     */
    public function refundStatus($id)
    {
        return response()->json(
            Refund::where('id', $id)->where('user_id', auth('api')->id())->firstOrFail()
        );
    }
}
