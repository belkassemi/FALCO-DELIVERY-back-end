<?php

namespace App\Policies;

use App\Models\Restaurant;
use App\Models\User;

class RestaurantPolicy
{
    /**
     * Ensure only the owner can manage the restaurant.
     */
    public function manage(User $user, Restaurant $restaurant): bool
    {
        return $user->id === $restaurant->user_id || $user->role === 'admin';
    }
}
