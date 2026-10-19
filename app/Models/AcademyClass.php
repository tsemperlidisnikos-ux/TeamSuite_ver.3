<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClub;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademyClass extends Model
{
    use BelongsToClub, HasUuids, SoftDeletes;

    protected $table = 'academy_classes';

    protected $fillable = [
        'club_id',
        'coach_id',
        'club_season_id',
        'name',
        'sport',
        'age_group',
        'gender',
        'max_students',
        'monthly_fee',
        'schedule_summary',
        'legacy_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'monthly_fee' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(ClubSeason::class, 'club_season_id');
    }

    public function athletes(): BelongsToMany
    {
        return $this->belongsToMany(Athlete::class, 'academy_class_athlete')
            ->withPivot(['is_primary'])
            ->withTimestamps();
    }
}
