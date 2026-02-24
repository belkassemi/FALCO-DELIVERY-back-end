<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine if the user can view the order.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->customer_id || 
               $user->id === $order->courier_id || 
               $user->role === 'admin' ||
               ($user->role === 'restaurant_owner' && $user->id === $order->restaurant->user_id);
    }

    /**
     * Determine if the customer can cancel the order.
     */
    public function cancel(User $user, Order $order): bool
    {
        return $user->id === $order->customer_id && $order->canBeCancelled();
    }

    /**
     * Determine if the courier can accept/update the order.
     */
    public function courierAction(User $user, Order $order): bool
    {
        return $user->role === 'courier' && ($order->courier_id === null || $order->courier_id === $user->id);
    }

    /**
     * Determine if the restaurant can manage the order.
     */
    public function restaurantAction(User $user, Order $order): bool
    {
        return $user->role === 'restaurant_owner' && $user->id === $order->restaurant->user_id;
    }
}
