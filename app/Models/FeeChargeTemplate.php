<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToClub;

class FeeChargeTemplate extends Model
{
    use HasUuids, SoftDeletes, BelongsToClub;


    protected $fillable = [
        'club_id',
        'name',
        'amount',
        'applies_to',
        'auto_generate',
        'is_active',
        'legacy_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'auto_generate' => 'boolean',
            'is_active' => 'boolean',
            'meta' => 'array',
        ];
    }
}
