<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneOtp extends Model
{
    protected $fillable = ['phone_number', 'otp_hash', 'expires_at', 'attempts', 'verified_at'];

    protected $casts = [
        'expires_at'  => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return now()->greaterThan($this->expires_at);
    }

    public function isMaxAttempts(): bool
    {
        return $this->attempts >= 3;
    }
}
