<?php

namespace App\Policies;

use App\Models\MenuItem;
use App\Models\User;

class MenuItemPolicy
{
    /**
     * Ensure only the restaurant owner can manage menu items.
     */
    public function manage(User $user, MenuItem $menuItem): bool
    {
        return $user->id === $menuItem->restaurant->user_id || $user->role === 'admin';
    }
}
