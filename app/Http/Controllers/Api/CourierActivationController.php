<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CourierActivationController extends Controller
{
    /**
     * Activate courier account using their one-time code
     */
    public function activate(Request $request)
    {
        $request->validate(['activation_code' => 'required|string']);

        $user = auth('api')->user();

        // Check if already activated
        if ($user->is_activated) {
            return response()->json(['message' => 'Account is already activated.'], 400);
        }

        // Check if locked
        if ($user->activation_locked_at || $user->activation_attempts >= 5) {
            return response()->json(['error' => 'Activation locked due to too many failed attempts. Please contact admin.'], 403);
        }

        // Check expiration
        if ($user->activation_expires_at && now()->isAfter($user->activation_expires_at)) {
            return response()->json(['error' => 'Activation code has expired. Please contact admin.'], 400);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($user, $request) {
            // Validate code
            if ($request->activation_code === $user->activation_code) {
                $user->update([
                    'is_activated' => true,
                    'activation_code' => null, // One-time use, zero it out
                    'activation_attempts' => 0,
                    'activation_expires_at' => null,
                ]);

                return response()->json(['message' => 'Courier account activated successfully']);
            }

            // Failed attempt
            $user->increment('activation_attempts');

            if ($user->activation_attempts >= 5) {
                // Lock and notify admin
                $user->update([
                    'activation_locked_at' => now(),
                    'activation_code'      => null,
                ]);
                Log::warning("Courier {$user->id} locked out of activation after 5 failed attempts.");
                return response()->json(['error' => 'Activation locked. Too many failed attempts.'], 403);
            }

            return response()->json([
                'error' => 'Invalid activation code.',
                'attempts_remaining' => 5 - $user->activation_attempts
            ], 400);
        });
    }
}
