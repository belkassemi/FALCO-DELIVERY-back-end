<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\TosAcceptance;

use App\Services\OtpService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    protected OtpService $otpService;
    protected SmsService $smsService;

    public function __construct(OtpService $otpService, SmsService $smsService)
    {
        $this->otpService = $otpService;
        $this->smsService = $smsService;
    }

    // ================================================================
    // PHONE-FIRST OTP AUTH (for customers — PRD lazy signup)
    // ================================================================

    /**
     * Step 1: Request OTP — send a 6-digit code via SMS.
     * No registration happens here. The user just provides a phone number.
     */
    public function requestOtp(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string|max:20|regex:/^\+?[0-9]{9,15}$/',
        ]);

        try {
            $otp = $this->otpService->generate($request->phone_number);
            $this->smsService->sendOtp($request->phone_number, $otp);

            return response()->json([
                'message' => 'OTP sent successfully. Valid for 3 minutes.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 429);
        }
    }

    /**
     * Step 2: Verify OTP + Lazy Signup.
     * If the phone number is new → create account automatically.
     * If existing → log the user in.
     * ToS acceptance is logged on first signup.
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string|max:20',
            'otp'          => 'required|string|size:6',
            'full_name'    => 'required_without:existing_user|string|max:255',
            'tos_accepted' => 'required|accepted', // Must check ToS
        ]);

        try {
            $this->otpService->verify($request->phone_number, $request->otp);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        // Find or create user
        $user = User::where('phone', $request->phone_number)->first();
        $isNewUser = false;

        if (!$user) {
            $isNewUser = true;
            $user = User::create([
                'name'             => $request->full_name,
                'phone'            => $request->phone_number,
                'role'             => 'customer',
                'status'           => 'active',
                'phone_verified_at' => now(),
            ]);


        } else {
            // Update phone_verified_at if not already set
            if (!$user->phone_verified_at) {
                $user->update(['phone_verified_at' => now()]);
            }
        }

        // Log ToS acceptance
        TosAcceptance::create([
            'user_id'     => $user->id,
            'tos_version' => '1.0',
            'ip_address'  => $request->ip(),
            'accepted_at' => now(),
        ]);

        // Check user status
        if ($user->status !== 'active') {
            return response()->json(['error' => 'Account suspended or banned.'], 403);
        }

        $token = auth('api')->login($user);

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
            'user'         => $user,
            'is_new_user'  => $isNewUser,
        ]);
    }

    // ================================================================
    // TRADITIONAL AUTH (for restaurant owners, admins — email+password)
    // ================================================================

    public function registerStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'            => 'required|string|max:255',
            'email'           => 'required|string|email|max:255|unique:users',
            'phone'           => 'required|string|max:20|unique:users',
            'password'        => 'required|string|min:6|confirmed',
            'store_name'      => 'required|string|max:255',
            'category_id'     => 'required|exists:categories,id',
            'address'         => 'nullable|string|max:255',
            'lat'             => 'required|numeric',
            'lng'             => 'required|numeric',
            'description'     => 'nullable|string',
            'image'           => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $user = null;
        $store = null;
        $fullUrl = null;

        DB::transaction(function () use ($request, &$user, &$store, &$fullUrl) {
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'phone'    => $request->phone,
                'password' => Hash::make($request->password),
                'role'     => 'restaurant_owner',
            ]);



            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('stores', 'public');
                $fullUrl = url('storage/' . $path);
            }

            $store = \App\Models\Store::create([
                'user_id'     => $user->id,
                'category_id' => $request->category_id,
                'name'        => $request->store_name,
                'address'     => $request->address,
                'phone'       => $request->phone,
                'description' => $request->description,
                'image'       => $fullUrl,
                'is_approved' => false,
                'is_open'     => false,
            ]);

            DB::statement(
                "UPDATE stores SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, updated_at = NOW() WHERE id = ?",
                [$request->lng, $request->lat, $store->id]
            );
        });

        $token = auth('api')->login($user);

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
            'user'         => $user,
            'store'        => $store,
            'message'      => 'Store registered successfully. Pending admin approval.'
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');
        $key = 'login:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => 'Too many login attempts. Try again in ' . ceil($seconds / 60) . ' minute(s).'
            ], 429);
        }

        if (!$token = auth('api')->attempt($credentials)) {
            RateLimiter::hit($key, 600);
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        RateLimiter::clear($key);

        $user = auth('api')->user();
        if ($user->status !== 'active') {
            auth('api')->logout();
            return response()->json(['message' => 'Account suspended or banned'], 403);
        }

        return $this->respondWithToken($token);
    }

    // ================================================================
    // COMMON AUTH ENDPOINTS
    // ================================================================

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

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6|confirmed',
        ]);

        $user = auth('api')->user();

        if (!$user->password || !Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Current password is incorrect.'], 400);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return response()->json(['message' => 'Password changed successfully.']);
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
            'user'         => auth('api')->user()
        ]);
    }
}
