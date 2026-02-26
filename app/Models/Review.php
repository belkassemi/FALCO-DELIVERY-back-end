<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    use SoftDeletes;

    protected $fillable = ['order_id', 'user_id', 'store_id', 'rating', 'comment'];

    protected $casts = ['created_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    // Backward compat alias
    public function restaurant()
    {
        return $this->store();
    }
}
