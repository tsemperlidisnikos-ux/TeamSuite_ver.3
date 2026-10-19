<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToClub;

class Attendance extends Model
{
    use HasUuids, SoftDeletes, BelongsToClub;


    protected $fillable = [
        'club_id',
        'training_id',
        'athlete_id',
        'status',
        'attended_on',
        'legacy_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'attended_on' => 'date',
            'meta' => 'array',
        ];
    }
}
