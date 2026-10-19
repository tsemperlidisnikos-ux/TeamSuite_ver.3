<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToClub;

class ClubSeason extends Model
{
    use HasUuids, SoftDeletes, BelongsToClub;

    protected $table = 'club_seasons';

    protected $fillable = [
        'club_id',
        'name',
        'starts_on',
        'ends_on',
        'is_active',
        'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
