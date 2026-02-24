<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CourierLocation extends Model
{
    protected $fillable = [
        'courier_id', 'location', 'is_online', 'vehicle_type'
    ];

    protected $casts = [
        'is_online' => 'boolean',
    ];

    public function courier()
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    /**
     * Scope to extract coordinates via PostGIS SQL.
     */
    public function scopeWithCoordinates($query)
    {
        return $query->addSelect([
            'lat_val' => DB::table('courier_locations')
                ->selectRaw('ST_Y(location::geometry)')
                ->whereColumn('id', 'courier_locations.id'),
            'lng_val' => DB::table('courier_locations')
                ->selectRaw('ST_X(location::geometry)')
                ->whereColumn('id', 'courier_locations.id'),
        ]);
    }

    /**
     * Find nearest courier using PostGIS KNN distance operator.
     */
    public function scopeNearestTo($query, $lat, $lng, $radiusKm = 10)
    {
        return $query->where('is_online', true)
            ->whereRaw("ST_DWithin(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)", [
                $lng, $lat, $radiusKm * 1000
            ])
            ->orderByRaw("location <-> ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography", [$lng, $lat]);
    }
}
