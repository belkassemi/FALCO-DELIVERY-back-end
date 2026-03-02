<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'store_id', 'courier_id', 'address_id',
        'status', 'total_price', 'delivery_fee', 'delivery_distance_km',
        'estimated_time', 'cancel_reason', 'payment_method', 'payment_status',
        'notes', 'age_confirmation', 'age_confirmation_at', 'promo_code_id',
        'customer_note', 'store_note', 'courier_note',
    ];

    protected $casts = [
        'total_price'          => 'float',
        'delivery_fee'         => 'float',
        'delivery_distance_km' => 'float',
        'age_confirmation'     => 'boolean',
        'age_confirmation_at'  => 'datetime',
    ];

    const STATUS_PENDING           = 'pending';
    const STATUS_STORE_NOTIFIED    = 'store_notified';
    const STATUS_STORE_CONFIRMED   = 'store_confirmed';
    const STATUS_COURIER_SEARCHING = 'courier_searching';
    const STATUS_COURIER_ASSIGNED  = 'courier_assigned';
    const STATUS_PREPARING         = 'preparing';
    const STATUS_READY             = 'ready';
    const STATUS_PICKED_UP         = 'picked_up';
    const STATUS_DELIVERED         = 'delivered';
    const STATUS_CANCELLED         = 'cancelled';
    const STATUS_REJECTED          = 'rejected';
    const STATUS_NO_COURIER_FOUND  = 'no_courier_found';

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

    public function courier()
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function promoCode()
    {
        return $this->belongsTo(Promotion::class, 'promo_code_id');
    }

    public function assignmentAttempts()
    {
        return $this->hasMany(OrderDispatchLog::class);
    }

    public function report()
    {
        return $this->hasOne(OrderReport::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_STORE_NOTIFIED,
        ]);
    }

    public function hasAgeRestrictedItems(): bool
    {
        return $this->items()
            ->whereHas('product', fn($q) => $q->where('is_age_restricted', true))
            ->exists();
    }
}
