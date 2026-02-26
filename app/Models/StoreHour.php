<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreHour extends Model
{
    protected $fillable = ['store_id', 'day_of_week', 'open_time', 'close_time', 'is_closed'];

    protected $casts = [
        'is_closed' => 'boolean',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
