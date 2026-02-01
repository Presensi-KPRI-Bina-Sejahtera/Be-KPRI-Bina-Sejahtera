<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PresenceLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
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
}
