<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToClub;

class SportMatch extends Model
{
    use HasUuids, SoftDeletes, BelongsToClub;

    protected $table = 'matches';

    protected $fillable = [
        'club_id',
        'academy_class_id',
        'starts_at',
        'opponent',
        'venue',
        'home_away',
        'score_for',
        'score_against',
        'status',
        'legacy_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
