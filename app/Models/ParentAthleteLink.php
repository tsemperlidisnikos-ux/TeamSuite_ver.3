<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToClub;

class ParentAthleteLink extends Model
{
    use HasUuids, SoftDeletes, BelongsToClub;

    protected $table = 'parent_athlete_links';

    protected $fillable = [
        'club_id',
        'parent_user_id',
        'athlete_id',
        'relation',
        'receives_push',
        'receives_sms',
        'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'receives_push' => 'boolean',
            'receives_sms' => 'boolean',
        ];
    }

    public function athlete()
    {
        return $this->belongsTo(Athlete::class);
    }

    public function parentUser()
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }
}
