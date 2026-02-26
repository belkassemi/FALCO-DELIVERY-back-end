<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TosAcceptance extends Model
{
    protected $fillable = ['user_id', 'tos_version', 'ip_address', 'accepted_at'];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
