<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send notification to a specific user and store in database.
     */
    public function send(User $user, string $title, string $body, string $type = 'system', array $data = [])
    {
        try {
            // 1. Store in database
            AppNotification::create([
                'user_id' => $user->id,
                'title'   => $title,
                'body'    => $body,
                'type'    => $type,
                'data'    => $data,
            ]);

            // 2. Log for now (In real implementation, trigger Push Notifications / WebSockets here)
            Log::info("Notification sent to User #{$user->id}: {$title} - {$body}", ['data' => $data]);

            // 3. TODO: Implement FCM / OneSignal / Pusher integration
            
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send notification: " . $e->getMessage());
            return false;
        }
    }

    public function sendToCourier(User $courier, string $title, string $body, array $data = [])
    {
        return $this->send($courier, $title, $body, 'courier_request', $data);
    }

    public function sendToRestaurant(User $owner, string $title, string $body, array $data = [])
    {
        return $this->send($owner, $title, $body, 'restaurant_order', $data);
    }

    public function sendToCustomer(User $customer, string $title, string $body, array $data = [])
    {
        return $this->send($customer, $title, $body, 'order_update', $data);
    }
}
