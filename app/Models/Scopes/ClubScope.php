<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ClubScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->bound('currentClub') && app('currentClub')) {
            $builder->where($model->getTable().'.club_id', app('currentClub')->id);
        }
    }
}
