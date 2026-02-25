<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'phone'    => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:6|confirmed',
            // Enforce customer role only for public registration
            'role'     => 'sometimes|string|in:customer',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'phone'    => $request->phone,
            'password' => Hash::make($request->password),
            'role'     => 'customer',
        ]);

        // Create Wallet for new user
        Wallet::create(['user_id' => $user->id, 'balance' => 0]);

        $token = auth('api')->login($user);

        return $this->respondWithToken($token);
    }

    public function registerRestaurant(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // User validations
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|string|email|max:255|unique:users',
            'phone'                 => 'required|string|max:20|unique:users',
            'password'              => 'required|string|min:6|confirmed',
            // Restaurant validations
            'restaurant_name'       => 'required|string|max:255',
            'category'              => 'nullable|string|max:255',
            'address'               => 'nullable|string|max:255',
            'lat'                   => 'required|numeric',
            'lng'                   => 'required|numeric',
            'description'           => 'nullable|string',
            'image'                 => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $user = null;
        $restaurant = null;
        $fullUrl = null;

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, &$user, &$restaurant, &$fullUrl) {
            // 1. Create User
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'phone'    => $request->phone,
                'password' => Hash::make($request->password),
                'role'     => 'restaurant_owner',
            ]);

            // 2. Create Wallet
            Wallet::create(['user_id' => $user->id, 'balance' => 0]);

            // 3. Handle Image
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('restaurants', 'public');
                $fullUrl = url('storage/' . $path);
            }

            // 4. Create Restaurant
            $restaurant = \App\Models\Restaurant::create([
                'user_id'     => $user->id,
                'name'        => $request->restaurant_name,
                'category'    => $request->category,
                'address'     => $request->address,
                'phone'       => $request->phone, // Defaulting to user's phone for restaurant contact
                'description' => $request->description,
                'image'       => $fullUrl,
                'is_approved' => false, // Requires admin approval
                'is_open'     => false,
            ]);

            // 5. Update Location
            \Illuminate\Support\Facades\DB::statement(
                "UPDATE restaurants SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, updated_at = NOW() WHERE id = ?",
                [$request->lng, $request->lat, $restaurant->id]
            );
        });

        $token = auth('api')->login($user);

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
            'user'         => $user,
            'restaurant'   => $restaurant,
            'message'      => 'Restaurant registered successfully. Pending admin approval.'
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        $key = 'login:' . $request->ip();

        // Check if IP is currently locked out
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($key);
            return response()->json([
                'message' => 'Too many login attempts. Try again in ' . ceil($seconds / 60) . ' minute(s).'
            ], 429);
        }

        if (!$token = auth('api')->attempt($credentials)) {
            // Record failed attempt — locks for 10 minutes (600s) after 5 failures
            \Illuminate\Support\Facades\RateLimiter::hit($key, 600);
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Clear attempts on successful login
        \Illuminate\Support\Facades\RateLimiter::clear($key);

        $user = auth('api')->user();
        if ($user->status !== 'active') {
            auth('api')->logout();
            return response()->json(['message' => 'Account suspended or banned'], 403);
        }

        return $this->respondWithToken($token);
    }

    public function profile()
    {
        return response()->json(auth('api')->user());
    }

    public function logout()
    {
        auth('api')->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    public function refresh()
    {
        return $this->respondWithToken(auth('api')->refresh());
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $user  = User::where('email', $request->email)->first();
        $token = \Illuminate\Support\Str::random(64);

        $user->update([
            'password_reset_token'      => $token,
            'password_reset_expires_at' => now()->addHour(),
        ]);

        // In production: dispatch a Mail::to($user)->send(new PasswordResetMail($token))
        return response()->json(['message' => 'Password reset link sent to your email.']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'                 => 'required|string',
            'password'              => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required',
        ]);

        $user = User::where('password_reset_token', $request->token)
            ->where('password_reset_expires_at', '>', now())
            ->first();

        if (!$user) {
            return response()->json(['error' => 'Invalid or expired password reset token.'], 400);
        }

        $user->update([
            'password'                  => Hash::make($request->password),
            'password_reset_token'      => null,
            'password_reset_expires_at' => null,
        ]);

        return response()->json(['message' => 'Password reset successfully. You can now log in.']);
    }

    public function verifyEmail(Request $request)
    {
        $request->validate(['token' => 'required|string']);

        $user = User::where('email_verification_token', $request->token)->first();

        if (!$user) {
            return response()->json(['error' => 'Invalid verification token.'], 400);
        }

        $user->update([
            'email_verified'            => true,
            'email_verification_token'  => null,
            'email_verified_at'         => now(),
        ]);

        return response()->json(['message' => 'Email verified successfully.']);
    }

    public function resendVerification()
    {
        $user  = auth('api')->user();

        if ($user->email_verified) {
            return response()->json(['message' => 'Email is already verified.'], 400);
        }

        $token = \Illuminate\Support\Str::random(64);
        $user->update(['email_verification_token' => $token]);

        // In production: dispatch Mail to send verification link
        return response()->json(['message' => 'Verification email resent.']);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6|confirmed',
        ]);

        $user = auth('api')->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Current password is incorrect.'], 400);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
