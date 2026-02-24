<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function show()
    {
        $wallet = auth('api')->user()->wallet;
        return response()->json($wallet);
    }

    public function topUp(Request $request)
    {
        $request->validate(['amount' => 'required|numeric|min:1']);
        
        $wallet = auth('api')->user()->wallet;
        $wallet->credit($request->amount, 'top_up', 'Wallet top-up');

        return response()->json(['message' => 'Wallet topped up', 'balance' => $wallet->balance]);
    }

    public function refund(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string'
        ]);
        
        $wallet = auth('api')->user()->wallet;
        $wallet->debit($request->amount, 'refund', $request->description);

        return response()->json(['message' => 'Refund processed', 'balance' => $wallet->balance]);
    }

    public function history()
    {
        $wallet = auth('api')->user()->wallet;
        return response()->json($wallet->transactions()->latest()->get());
    }
}
