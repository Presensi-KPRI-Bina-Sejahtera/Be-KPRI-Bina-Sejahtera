<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PresenceLocation;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Http;

class PresenceLocationController extends Controller
{
    /**
     * Dapatkan alamat dari koordinat latitude dan longitude menggunakan Nominatim OpenStreetMap.
     */
    public function getAddressFromCoordinates($latitude, $longitude)
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

    /**
     * Dapat alamat dari koordinat latitude dan longitude.
     * GET /api/admin/presence-location/address
     */
    public function getAddressFromCoordinatesApi(Request $request) : Response
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ], [
            'latitude.required' => 'Latitude wajib diisi',
            'latitude.numeric' => 'Latitude harus berupa angka',
            'longitude.required' => 'Longitude wajib diisi',
            'longitude.numeric' => 'Longitude harus berupa angka',
        ]);

        $address = $this->getAddressFromCoordinates($validated['latitude'], $validated['longitude']);

        return response()->json([
            'status' => $address ? 'success' : 'error',
            'message' => $address ? 'Alamat berhasil diambil' : 'Alamat tidak ditemukan',
            'data' => [
                'address' => $address,
            ],
        ]);
    }

    /**
     * Ambil daftar semua presence location.
     * GET /admin/presence-location
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'search' => ['sometimes', 'string'],
        ], [
            'per_page.integer' => 'per_page harus berupa angka',
            'per_page.min' => 'per_page minimal 1',
            'search.string' => 'Search harus berupa teks',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = $validated['search'] ?? null;

        $query = PresenceLocation::query()->orderBy('id', 'asc');
        if ($search) {
            $query->where('name', 'like', "%$search%");
        }

        $locations = $query->paginate($perPage);
        $locationData = collect($locations->items())->map(function ($location) {
            return [
                'id' => (int) $location->id,
                'name' => $location->name,
                'address' => $location->address,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'max_distance' => (int) $location->max_distance,
                'maps' => "https://www.google.com/maps/search/?api=1&query={$location->latitude},{$location->longitude}",
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar lokasi presensi berhasil diambil',
            'data' => [
                'current_page' => (int) $locations->currentPage(),
                'last_page' => (int) $locations->lastPage(),
                'per_page' => (int) $locations->perPage(),
                'total' => (int) $locations->total(),
                'presence_locations' => $locationData,
            ],
        ]);
    }

    /**
     * Ambil daftar presence location untuk dropdown.
     * GET /admin/presence-location/dropdown
     */
    public function dropdown(Request $request): Response
    {
        $locations = PresenceLocation::query()->orderBy('id', 'asc')->get(['id', 'name']);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar lokasi presensi untuk dropdown berhasil diambil',
            'data' => $locations,
        ]);
    }

    /**
     * Tambah presence location baru.
     * POST /admin/presence-location
     */
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('presence_locations', 'name')],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'string', 'max:50'],
            'longitude' => ['required', 'string', 'max:50'],
            'max_distance' => ['nullable', 'integer', 'min:0'],
        ], [
            'name.required' => 'Nama lokasi presensi wajib diisi',
            'name.string' => 'Nama lokasi presensi harus berupa teks',
            'name.max' => 'Nama lokasi presensi maksimal :max karakter',
            'name.unique' => 'Nama lokasi presensi sudah digunakan',
            'address.string' => 'Alamat harus berupa teks',
            'address.max' => 'Alamat maksimal :max karakter',
            'latitude.required' => 'Latitude wajib diisi',
            'latitude.string' => 'Latitude harus berupa teks',
            'latitude.max' => 'Latitude maksimal :max karakter',
            'longitude.required' => 'Longitude wajib diisi',
            'longitude.string' => 'Longitude harus berupa teks',
            'longitude.max' => 'Longitude maksimal :max karakter',
            'max_distance.integer' => 'Max distance harus berupa angka',
            'max_distance.min' => 'Max distance minimal :min',
        ]);

        if (!isset($validated['max_distance'])) {
            $validated['max_distance'] = 50; // default 50 meters
        }

        if (!isset($validated['address'])) {
            $validated['address'] = $this->getAddressFromCoordinates($validated['latitude'], $validated['longitude']);
        }

        $location = PresenceLocation::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Lokasi presensi berhasil ditambahkan',
            'data' => [
                'id' => (int) $location->id,
                'name' => $location->name,
                'address' => $location->address,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'max_distance' => (int) $location->max_distance,
                'maps' => "https://www.google.com/maps/search/?api=1&query={$location->latitude},{$location->longitude}",
            ],
        ], 201);
    }

    /**
     * Update presence location.
     * PUT /admin/presence-location/{id}
     */
    public function update(Request $request, $id): Response
    {
        $location = PresenceLocation::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('presence_locations', 'name')->ignore($location->id)],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitude' => ['sometimes', 'string', 'max:50'],
            'longitude' => ['sometimes', 'string', 'max:50'],
            'max_distance' => ['sometimes', 'integer', 'min:0'],
        ], [
            'name.string' => 'Nama lokasi presensi harus berupa teks',
            'name.max' => 'Nama lokasi presensi maksimal :max karakter',
            'name.unique' => 'Nama lokasi presensi sudah digunakan',
            'address.string' => 'Alamat harus berupa teks',
            'address.max' => 'Alamat maksimal :max karakter',
            'latitude.string' => 'Latitude harus berupa teks',
            'latitude.max' => 'Latitude maksimal :max karakter',
            'longitude.string' => 'Longitude harus berupa teks',
            'longitude.max' => 'Longitude maksimal :max karakter',
            'max_distance.integer' => 'Max distance harus berupa angka',
            'max_distance.min' => 'Max distance minimal :min',
        ]);

        // kalau latitude atau longitude diupdate, dan address tidak diupdate, ambil address baru
        if ((isset($validated['latitude']) || isset($validated['longitude'])) && !isset($validated['address'])) {
            $lat = $validated['latitude'] ?? $location->latitude;
            $lon = $validated['longitude'] ?? $location->longitude;
            $validated['address'] = $this->getAddressFromCoordinates($lat, $lon);
        }

        $location->update($validated);
        $location->refresh();

        return response()->json([
            'status' => 'success',
            'message' => 'Lokasi presensi berhasil diupdate',
            'data' => [
                'id' => (int) $location->id,
                'name' => $location->name,
                'address' => $location->address,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'max_distance' => (int) $location->max_distance,
                'maps' => "https://www.google.com/maps/search/?api=1&query={$location->latitude},{$location->longitude}",
            ],
        ]);
    }

    /**
     * Hapus presence location.
     * DELETE /admin/presence-location/{id}
     */
    public function destroy($id): Response
    {
        $location = PresenceLocation::findOrFail($id);
        $location->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Lokasi presensi berhasil dihapus',
        ]);
    }
}
