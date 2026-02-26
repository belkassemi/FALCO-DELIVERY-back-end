<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuChangeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'product_id', 'action_type', 'proposed_data',
        'old_data', 'requested_by', 'status', 'admin_id',
        'reviewed_at', 'rejection_reason',
    ];

    protected $casts = [
        'proposed_data' => 'array',
        'old_data'      => 'array',
        'reviewed_at'   => 'datetime',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    // Backward compat alias
    public function restaurant()
    {
        return $this->store();
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Backward compat alias
    public function menuItem()
    {
        return $this->product();
    }
}
