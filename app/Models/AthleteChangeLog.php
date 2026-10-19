<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Concerns\BelongsToClub;

class AthleteChangeLog extends Model
{
    use HasUuids, BelongsToClub;

    protected $table = 'athlete_change_logs';

    protected $fillable = [
        'club_id',
        'athlete_id',
        'user_id',
        'field',
        'old_value',
        'new_value',
        'legacy_id',
    ];
}
