<?php

namespace App\Support;

use App\Models\Club;
use App\Models\User;
use App\Services\Platform\PlatformConfigService;

final class ClubModuleAccess
{
    /**
     * Map named route prefixes / exact names to academy module ids.
     *
     * @var array<string, string>
     */
    private const ROUTE_MODULE = [
        'dashboard' => 'dashboard',
        'admin.athletes' => 'athletes',
        'admin.attendance' => 'attendance',
        'admin.transactions' => 'transactions',
        'admin.classes' => 'classes',
        'admin.fees' => 'fees',
        'admin.announcements' => 'announcements',
        'admin.coaches' => 'coaches',
        'admin.staff' => 'staff',
        'admin.members' => 'members',
        'admin.trainings' => 'trainings',
        'admin.matches' => 'matches',
        'admin.schedule' => 'schedule',
        'admin.warehouse' => 'warehouse',
        'admin.rentals' => 'rental',
        'admin.photos' => 'photos',
        'admin.partners' => 'partnerBusinesses',
        'admin.protocol' => 'documentProtocol',
        'admin.associations' => 'associations',
        'admin.sports' => 'settings',
        'admin.parents' => 'parents',
        'admin.calendar' => 'calendar',
        'admin.finance' => 'finance',
        'admin.prints' => 'prints',
        'admin.settings' => 'settings',
        'admin.receipts' => 'settings',
        'admin.setup' => 'settings',
    ];

    public static function moduleForRoute(?string $routeName): ?string
    {
        if ($routeName === null || $routeName === '') {
            return null;
        }

        if (isset(self::ROUTE_MODULE[$routeName])) {
            return self::ROUTE_MODULE[$routeName];
        }

        foreach (self::ROUTE_MODULE as $prefix => $module) {
            if (str_starts_with($routeName, $prefix.'.') || $routeName === $prefix) {
                return $module;
            }
        }

        return null;
    }

    public static function userCanAccessModule(User $user, string $moduleId, ?Club $club = null): bool
    {
        if ($user->is_platform_admin) {
            return true;
        }

        $club ??= $user->activeClub;
        if (! $club) {
            return false;
        }

        $clubModules = app(PlatformConfigService::class)->modulesForClub($club->id);
        $allowed = $user->allowedModulesInClub($clubModules, $club);

        return in_array($moduleId, $allowed, true);
    }
}
