<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreClosure extends Model
{
    protected $fillable = ['store_id', 'closed_date', 'reason'];

    protected $casts = [
        'closed_date' => 'date',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
