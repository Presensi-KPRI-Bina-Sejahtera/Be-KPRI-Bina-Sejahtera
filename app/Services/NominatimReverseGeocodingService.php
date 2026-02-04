<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class NominatimReverseGeocodingService
{
    /**
     * Dapatkan alamat dari koordinat latitude dan longitude menggunakan Nominatim OpenStreetMap.
     */
    public function getAddressFromCoordinates($latitude, $longitude): ?string
    {
        $userAgent = env('NOMINATIM_USER_AGENT', 'Presensi KRPIBS/1.0 (presensi@trisuladana.com)');

        $response = Http::withHeaders([
            'User-Agent' => $userAgent,
        ])->get('https://nominatim.openstreetmap.org/reverse', [
            'lat' => (float) $latitude,
            'lon' => (float) $longitude,
            'format' => 'json',
        ]);

        return $response['display_name'] ?? null;
    }
}
