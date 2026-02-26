<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'avatar', 'role', 'status',
        'phone_verified_at', 'device_token',
        'activation_code', 'activation_expires_at', 'activation_attempts',
        'is_activated', 'activation_locked_at',
        'password_reset_token', 'password_reset_expires_at',
    ];

    protected $hidden = ['password', 'remember_token', 'activation_code'];

    protected $casts = [
        'email_verified_at'         => 'datetime',
        'phone_verified_at'         => 'datetime',
        'activation_expires_at'     => 'datetime',
        'activation_locked_at'      => 'datetime',
        'password_reset_expires_at' => 'datetime',
        'is_activated'              => 'boolean',
    ];

    // JWT
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return ['role' => $this->role];
    }

    // Relationships
    public function store()
    {
        return $this->hasOne(Store::class);
    }

    // Keep 'restaurant()' as alias for backward compat during transition
    public function restaurant()
    {
        return $this->store();
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function courierOrders()
    {
        return $this->hasMany(Order::class, 'courier_id');
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function favorites()
    {
        return $this->belongsToMany(Store::class, 'favorites', 'user_id', 'store_id');
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function courierLocation()
    {
        return $this->hasOne(CourierLocation::class, 'courier_id');
    }

    public function notifications()
    {
        return $this->hasMany(AppNotification::class);
    }

    public function courierEarnings()
    {
        return $this->hasMany(CourierEarning::class, 'courier_id');
    }

    public function tosAcceptances()
    {
        return $this->hasMany(TosAcceptance::class);
    }
}
