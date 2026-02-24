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
        'name', 'email', 'phone', 'password', 'avatar', 'role', 'status', 'phone_verified_at',
        'activation_code', 'activation_expires_at', 'activation_attempts', 'is_activated', 'activation_locked_at'
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
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
    public function restaurant()
    {
        return $this->hasOne(Restaurant::class);
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
        return $this->belongsToMany(Restaurant::class, 'favorites');
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
}
