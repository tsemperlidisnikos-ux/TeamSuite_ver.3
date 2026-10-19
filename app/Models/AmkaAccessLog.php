<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Concerns\BelongsToClub;

class AmkaAccessLog extends Model
{
    use HasUuids, BelongsToClub;


    protected $fillable = [
        'club_id',
        'athlete_id',
        'user_id',
        'action',
        'legacy_id',
    ];
}
