<?php

namespace App\Http\Middleware;

use App\Support\ClubHome;
use App\Support\ClubModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClubModule
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, ?string $module = null): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        if ($user->is_platform_admin) {
            return $next($request);
        }

        $moduleId = $module ?: ClubModuleAccess::moduleForRoute($request->route()?->getName());
        if ($moduleId === null) {
            return $next($request);
        }

        $club = $request->attributes->get('currentClub') ?? $user->activeClub;
        if (! ClubModuleAccess::userCanAccessModule($user, $moduleId, $club)) {
            if ($request->expectsJson()) {
                abort(403, 'Δεν έχετε πρόσβαση σε αυτή την ενότητα.');
            }

            return redirect()->route(ClubHome::routeName($user));
        }

        return $next($request);
    }
}
