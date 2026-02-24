<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'restaurant_id', 'name', 'description', 'price', 'category',
        'image', 'is_available', 'requires_prescription', 'is_pending_approval'
    ];

    protected $casts = [
        'is_available'          => 'boolean',
        'requires_prescription' => 'boolean',
        'price'                 => 'float',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
