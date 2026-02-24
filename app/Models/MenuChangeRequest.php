<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuChangeRequest extends Model
{
    protected $fillable = [
        'restaurant_id', 'menu_item_id', 'action_type', 
        'proposed_data', 'old_data', 'status', 'admin_id', 
        'requested_by', 'rejection_reason', 'reviewed_at'
    ];

    protected $casts = [
        'proposed_data' => 'array',
        'reviewed_at'   => 'datetime',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
