<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PresenceLocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'latitude',
        'longitude',
        'max_distance',
    ];

    protected $casts = [
        'max_distance' => 'integer',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public static function calculateDistance(?PresenceLocation $location, float $latitude, float $longitude): int
    {
        if (!$location) {
            return PHP_INT_MAX;
        }

        $earthRadius = 6371e3; // in meters

        $lat1 = deg2rad($location->latitude);
        $lat2 = deg2rad($latitude);
        $deltaLat = deg2rad($latitude - $location->latitude);
        $deltaLon = deg2rad($longitude - $location->longitude);

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1) * cos($lat2) *
             sin($deltaLon / 2) * sin($deltaLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) floor($earthRadius * $c); // distance in meters (integer)
    }
}
