<?php

namespace App\Support;

use App\Models\User;
use App\Services\Platform\PlatformConfigService;

final class ClubHome
{
    /**
     * Named route for the user's first allowed club screen (or platform home).
     */
    public static function routeName(?User $user): string
    {
        if (! $user) {
            return 'login';
        }

        if ($user->is_platform_admin) {
            return 'platform.dashboard';
        }

        $club = $user->activeClub;
        if (! $club) {
            return 'dashboard';
        }

        $modules = $user->allowedModulesInClub(
            app(PlatformConfigService::class)->modulesForClub($club->id),
            $club,
        );

        $role = (string) $user->roleInClub($club);

        // TeamSuite HomeRoute: coach/athlete land on their portal (not admin dashboard).
        if ($role === Roles::COACH && in_array('dashboard', $modules, true)) {
            return 'portal.coach';
        }

        if ($role === Roles::ATHLETE) {
            return 'portal.athlete';
        }

        if (in_array('dashboard', $modules, true)) {
            return 'dashboard';
        }

        $items = Navigation::adminItems($modules);
        $first = $items[0]['route'] ?? null;
        if (is_string($first) && $first !== '' && $first !== 'dashboard') {
            return $first;
        }

        if ($role === Roles::PARENT) {
            return 'portal.parent';
        }

        return 'dashboard';
    }

    public static function url(?User $user): string
    {
        return route(self::routeName($user));
    }

    public static function canAccessDashboard(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->is_platform_admin) {
            return true;
        }

        $club = $user->activeClub;
        if (! $club) {
            return false;
        }

        return in_array('dashboard', $user->allowedModulesInClub(
            app(PlatformConfigService::class)->modulesForClub($club->id),
            $club,
        ), true);
    }
}
