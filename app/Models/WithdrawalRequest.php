<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawalRequest extends Model
{
    protected $fillable = [
        'courier_id', 'amount', 'status', 'admin_id', 'admin_note', 'processed_at'
    ];

    protected $casts = ['processed_at' => 'datetime'];

    public function courier() { return $this->belongsTo(User::class, 'courier_id'); }
    public function admin()   { return $this->belongsTo(User::class, 'admin_id'); }
}
