<?php

namespace App\Models\Concerns;

use App\Models\Club;
use App\Models\Scopes\ClubScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToClub
{
    public static function bootBelongsToClub(): void
    {
        static::addGlobalScope(new ClubScope);

        static::creating(function ($model) {
            if (! $model->club_id && app()->bound('currentClub')) {
                $model->club_id = app('currentClub')->id;
            }
        });
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
