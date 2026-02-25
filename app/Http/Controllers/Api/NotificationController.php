<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        return response()->json(auth('api')->user()->notifications()->latest()->get());
    }

    public function markRead($id)
    {
        $notification = auth('api')->user()->notifications()->findOrFail($id);
        $notification->update(['read_at' => now()]);

        return response()->json(['message' => 'Notification marked as read']);
    }

    public function registerDeviceToken(Request $request)
    {
        $request->validate(['device_token' => 'required|string']);
        auth('api')->user()->update(['device_token' => $request->device_token]);
        return response()->json(['message' => 'Device token registered successfully.']);
    }

    public function markAllRead()
    {
        auth('api')->user()->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
