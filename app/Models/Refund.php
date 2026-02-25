<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $fillable = [
        'user_id', 'order_id', 'amount', 'reason', 'status', 'admin_id', 'admin_note', 'processed_at'
    ];

    protected $casts = ['processed_at' => 'datetime'];

    public function user()    { return $this->belongsTo(User::class); }
    public function order()   { return $this->belongsTo(Order::class); }
    public function admin()   { return $this->belongsTo(User::class, 'admin_id'); }
}
