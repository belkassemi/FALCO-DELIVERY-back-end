<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierMonthlyStat extends Model
{
    protected $fillable = [
        'courier_id', 'month', 'total_deliveries',
        'total_distance_km', 'avg_delivery_time_min',
    ];

    protected $casts = [
        'total_distance_km'     => 'float',
        'avg_delivery_time_min' => 'float',
    ];

    public function courier()
    {
        return $this->belongsTo(User::class, 'courier_id');
    }
}
