<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToClub;

class Facility extends Model
{
    use HasUuids, SoftDeletes, BelongsToClub;


    protected $fillable = [
        'club_id',
        'name',
        'address',
        'is_rentable',
        'legacy_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_rentable' => 'boolean',
            'meta' => 'array',
        ];
    }
}
