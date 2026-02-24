<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDispatchLog extends Model
{
    protected $fillable = ['order_id', 'courier_id', 'status', 'responded_at'];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function courier()
    {
        return $this->belongsTo(User::class, 'courier_id');
    }
}
