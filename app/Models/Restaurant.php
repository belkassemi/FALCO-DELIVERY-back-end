<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Restaurant extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'category', 'address', 'location',
        'phone', 'image', 'description', 'rating', 'is_open', 'is_approved'
    ];

    /**
     * Scope to automatically include lat_val and lng_val via PostGIS.
     */
    public function scopeWithCoordinates($query)
    {
        return $query->addSelect([
            'lat_val' => DB::table('restaurants')
                ->selectRaw('ST_Y(location::geometry)')
                ->whereColumn('id', 'restaurants.id'),
            'lng_val' => DB::table('restaurants')
                ->selectRaw('ST_X(location::geometry)')
                ->whereColumn('id', 'restaurants.id'),
        ]);
    }

    /**
     * Scope for finding nearby restaurants using PostGIS geography.
     */
    public function scopeNearby($query, $lat, $lng, $radiusKm = 10)
    {
        return $query->whereRaw(
            "ST_DWithin(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)",
            [$lng, $lat, $radiusKm * 1000]
        )->orderByRaw(
            "location <-> ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography",
            [$lng, $lat]
        );
    }

    protected $casts = [
        'is_open'     => 'boolean',
        'is_approved' => 'boolean',
        'rating'      => 'float',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function menuItems()
    {
        return $this->hasMany(MenuItem::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function favoritedBy()
    {
        return $this->belongsToMany(User::class, 'favorites');
    }
}
