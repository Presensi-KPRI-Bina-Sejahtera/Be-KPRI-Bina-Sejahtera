<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cashflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'type',
        'value',
    ];

    protected $casts = [
        'date' => 'date',
        'value' => 'integer',
    ];

    public function user(): BelongsTo
    {
        // Antisipasi jika user di-soft delete: data cashflow tetap bisa di-load.
        return $this->belongsTo(User::class)->withTrashed();
    }
}
