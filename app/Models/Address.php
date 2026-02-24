<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'label', 'street', 'city', 'location', 'is_default'
    ];

    /**
     * Scope to automatically include lat_val and lng_val via PostGIS.
     */
    public function scopeWithCoordinates($query)
    {
        return $query->addSelect([
            'lat_val' => DB::table('addresses')
                ->selectRaw('ST_Y(location::geometry)')
                ->whereColumn('id', 'addresses.id'),
            'lng_val' => DB::table('addresses')
                ->selectRaw('ST_X(location::geometry)')
                ->whereColumn('id', 'addresses.id'),
        ]);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
