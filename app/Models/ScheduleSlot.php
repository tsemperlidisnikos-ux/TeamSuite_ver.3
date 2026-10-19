<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToClub;

class ScheduleSlot extends Model
{
    use HasUuids, SoftDeletes, BelongsToClub;

    protected $table = 'schedule_slots';

    protected $fillable = [
        'club_id',
        'academy_class_id',
        'facility_id',
        'weekday',
        'starts_at',
        'ends_at',
        'title',
        'legacy_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}
