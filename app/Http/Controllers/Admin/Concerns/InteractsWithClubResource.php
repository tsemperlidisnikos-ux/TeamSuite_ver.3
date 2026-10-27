<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Scopes\ClubScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait InteractsWithClubResource
{
    protected function clubId(Request $request): string
    {
        $club = $request->attributes->get('currentClub') ?? $request->user()?->activeClub;
        abort_unless($club, 403, 'Δεν έχει οριστεί σύλλογος.');

        return $club->id;
    }

    protected function clubQuery(string $modelClass, Request $request): Builder
    {
        /** @var Model $modelClass */
        return $modelClass::query()
            ->withoutGlobalScope(ClubScope::class)
            ->where('club_id', $this->clubId($request));
    }
}
