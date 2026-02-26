<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id', 'name', 'description', 'price', 'category',
        'image', 'is_available', 'is_age_restricted', 'is_pending',
    ];

    protected $casts = [
        'price'             => 'float',
        'is_available'      => 'boolean',
        'is_age_restricted' => 'boolean',
        'is_pending'        => 'boolean',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'product_id');
    }
}
