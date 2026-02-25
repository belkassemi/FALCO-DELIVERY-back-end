<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class SocketController extends Controller
{
    /**
     * POST /api/socket/token
     * Generates a short-lived, single-use WebSocket handshake token.
     * Frontend uses this token to authenticate the WebSocket upgrade request.
     */
    public function generateToken()
    {
        $user  = auth('api')->user();
        $token = Str::random(64);

        // Store in cache for 30 seconds, keyed by the token
        Cache::put('ws_token:' . $token, [
            'user_id' => $user->id,
            'role'    => $user->role,
        ], now()->addSeconds(30));

        return response()->json([
            'socket_token' => $token,
            'expires_in'   => 30,
        ]);
    }
}
