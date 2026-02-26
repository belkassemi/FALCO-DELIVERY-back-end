<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierEarning extends Model
{
    protected $fillable = ['courier_id', 'order_id', 'amount', 'type'];

    protected $casts = [
        'amount' => 'float',
    ];

    public function courier()
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
