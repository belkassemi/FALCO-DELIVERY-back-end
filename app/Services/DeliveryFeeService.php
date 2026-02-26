<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Setting;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

class DeliveryFeeService
{
    /**
     * Calculate delivery fee based on Haversine distance × cost_per_km.
     *
     * @param  Store  $store       The store with lat/lng coordinates
     * @param  Address $address    The customer delivery address
     * @return array ['fee' => float, 'distance_km' => float]
     */
    public function calculate(Store $store, Address $address): array
    {
        // Extract store coordinates via PostGIS
        $storeCoords = DB::selectOne(
            "SELECT ST_Y(location::geometry) as lat, ST_X(location::geometry) as lng FROM stores WHERE id = ?",
            [$store->id]
        );

        // Extract address coordinates via PostGIS
        $addrCoords = DB::selectOne(
            "SELECT ST_Y(location::geometry) as lat, ST_X(location::geometry) as lng FROM addresses WHERE id = ?",
            [$address->id]
        );

        if (!$storeCoords || !$addrCoords || !$storeCoords->lat || !$addrCoords->lat) {
            // Fallback to default fee if coordinates missing
            return ['fee' => 10, 'distance_km' => 0];
        }

        $distanceKm = $this->haversine(
            $storeCoords->lat, $storeCoords->lng,
            $addrCoords->lat, $addrCoords->lng
        );

        $costPerKm = (float) Setting::getValue('cost_per_km', 5);
        $fee = round($distanceKm * $costPerKm, 2);

        // Enforce a minimum delivery fee
        $minFee = (float) Setting::getValue('min_delivery_fee', 5);
        $fee = max($fee, $minFee);

        return [
            'fee'         => $fee,
            'distance_km' => round($distanceKm, 2),
        ];
    }

    /**
     * Haversine formula — returns distance in kilometers.
     */
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
