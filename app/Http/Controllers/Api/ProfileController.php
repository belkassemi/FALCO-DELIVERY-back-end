<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = auth('api')->user();
        
        $validator = Validator::make($request->all(), [
            'name'  => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20|unique:users,phone,' . $user->id,
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $user->update($request->only('name', 'phone'));

        return response()->json([
            'message' => 'Profile updated successfully',
            'user'    => $user
        ]);
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user = auth('api')->user();

        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $user->update(['avatar' => $path]);
        }

        return response()->json([
            'message' => 'Avatar uploaded successfully',
            'avatar_url' => asset('storage/' . $path)
        ]);
    }

    // --- Addresses ---

    public function getAddresses()
    {
        // TECHNICAL CONSTRAINT: Use withCoordinates for spatial extraction
        return response()->json(auth('api')->user()->addresses()->withCoordinates()->get());
    }

    public function addAddress(Request $request)
    {
        $request->validate([
            'label'  => 'required|string',
            'street' => 'required|string',
            'city'   => 'nullable|string',
            'lat'    => 'required|numeric',
            'lng'    => 'required|numeric',
        ]);

        $user = auth('api')->user();

        // Create the base record
        $address = $user->addresses()->create($request->except(['lat', 'lng']));

        // TECHNICAL CONSTRAINT: Must use ST_SetSRID and ST_MakePoint for geography POINT
        \Illuminate\Support\Facades\DB::statement(
            "UPDATE addresses SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, updated_at = NOW() WHERE id = ?",
            [$request->lng, $request->lat, $address->id]
        );

        return response()->json($address->refresh(), 201);
    }

    public function deleteAddress($id)
    {
        $address = auth('api')->user()->addresses()->find($id);
        if (!$address) return response()->json(['message' => 'Address not found'], 404);
        
        $address->delete();
        return response()->json(['message' => 'Address deleted successfully']);
    }

    // --- Favorites ---

    public function getFavorites()
    {
        return response()->json(auth('api')->user()->favorites);
    }

    public function toggleFavorite($restaurantId)
    {
        $user = auth('api')->user();
        $user->favorites()->toggle($restaurantId);
        
        return response()->json(['message' => 'Favorites updated']);
    }
}
