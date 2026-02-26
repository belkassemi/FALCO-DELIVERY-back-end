<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'category_id', 'name', 'category', 'address', 'phone',
        'description', 'image', 'rating', 'is_approved', 'is_open', 'location',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'is_open'     => 'boolean',
        'rating'      => 'float',
    ];

    // --- PostGIS Coordinate Extraction ---

    public function scopeWithCoordinates($query)
    {
        return $query->addSelect(
            DB::raw('ST_Y(location::geometry) as lat_val'),
            DB::raw('ST_X(location::geometry) as lng_val')
        );
    }

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

    // --- Store Hours Auto-Open Logic ---

    public function isCurrentlyOpen(): bool
    {
        // Check for exception closures first
        $today = now()->toDateString();
        if ($this->closures()->where('closed_date', $today)->exists()) {
            return false;
        }

        // Check weekly schedule
        $dayOfWeek = now()->dayOfWeek; // 0=Sunday
        $hour = $this->hours()->where('day_of_week', $dayOfWeek)->first();

        if (!$hour || $hour->is_closed) {
            return false;
        }

        $currentTime = now()->format('H:i:s');
        return $currentTime >= $hour->open_time && $currentTime <= $hour->close_time;
    }

    // --- Relationships ---

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function categoryRelation()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'store_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'store_id');
    }

    public function hours()
    {
        return $this->hasMany(StoreHour::class);
    }

    public function closures()
    {
        return $this->hasMany(StoreClosure::class);
    }
}
