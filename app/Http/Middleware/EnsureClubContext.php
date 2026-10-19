<?php

namespace App\Http\Middleware;

use App\Models\Club;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClubContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($user->is_platform_admin && ! $user->active_club_id) {
            // Platform admin may browse platform area without club; admin UI needs one.
            if ($request->routeIs('platform.*')) {
                return $next($request);
            }
        }

        $clubId = $user->active_club_id;

        if (! $clubId) {
            $membership = $user->clubs()->wherePivot('is_active', true)->first();
            if ($membership) {
                $user->forceFill(['active_club_id' => $membership->id])->save();
                $clubId = $membership->id;
            }
        }

        if (! $clubId) {
            abort(403, 'Δεν έχει οριστεί ενεργός σύλλογος.');
        }

        $club = Club::query()->find($clubId);

        if (! $club || ! $club->is_active) {
            abort(403, 'Ο σύλλογος δεν είναι διαθέσιμος.');
        }

        if (! $user->is_platform_admin && ! $user->clubs()->where('clubs.id', $club->id)->exists()) {
            abort(403, 'Δεν έχετε πρόσβαση σε αυτόν τον σύλλογο.');
        }

        app()->instance('currentClub', $club);
        $request->attributes->set('currentClub', $club);

        return $next($request);
    }
}
