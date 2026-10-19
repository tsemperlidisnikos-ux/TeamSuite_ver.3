<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToClub;

class ProgressReport extends Model
{
    use HasUuids, SoftDeletes, BelongsToClub;


    protected $fillable = [
        'club_id',
        'athlete_id',
        'coach_id',
        'report_date',
        'body',
        'legacy_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'meta' => 'array',
        ];
    }
}
