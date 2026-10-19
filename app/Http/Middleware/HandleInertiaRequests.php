<?php

namespace App\Http\Middleware;

use App\Models\RegistrationApplication;
use App\Models\WarehouseProduct;
use App\Services\Platform\PlatformConfigService;
use App\Support\Navigation;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $club = $request->attributes->get('currentClub') ?? $user?->activeClub;
        $platformConfigService = app(PlatformConfigService::class);
        $platformConfig = $user ? $platformConfigService->get() : [];
        $enabledModules = $club
            ? $platformConfigService->modulesForClub($club->id)
            : array_column(Navigation::academyModules(), 'id');

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_platform_admin' => (bool) $user->is_platform_admin,
                    'active_club_id' => $user->active_club_id,
                    'role' => $user->is_platform_admin
                        ? Roles::PLATFORM_ADMIN
                        : $user->roleInClub($club),
                    'role_label' => Roles::label(
                        $user->is_platform_admin
                            ? Roles::PLATFORM_ADMIN
                            : $user->roleInClub($club)
                    ),
                ] : null,
                'impersonating' => (bool) $request->session()->get('impersonator_id'),
            ],
            'currentClub' => $club ? [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
                'logo_url' => $club->logo_path
                    ? Storage::disk('public')->url($club->logo_path)
                    : null,
                // Platform Admin → Σύλλογος → Κεφαλίδα ανά σύλλογο (πριν το «Suite»).
                'header_title' => $platformConfigService->headerTitleForClub(
                    $club->id,
                    $club->name,
                ),
            ] : null,
            'navigation' => [
                'items' => Navigation::adminItems($enabledModules),
                'groups' => Navigation::adminGroups($enabledModules),
                'modules' => $enabledModules,
            ],
            'clubAlerts' => $club ? [
                'pendingRegistrations' => RegistrationApplication::query()->withoutGlobalScopes()
                    ->where('club_id', $club->id)
                    ->whereIn('status', ['pending', 'new', 'waiting'])
                    ->count(),
                'lowStock' => WarehouseProduct::query()->withoutGlobalScopes()
                    ->where('club_id', $club->id)
                    ->whereColumn('stock_qty', '<=', 'low_stock_threshold')
                    ->count(),
            ] : [
                'pendingRegistrations' => 0,
                'lowStock' => 0,
            ],
            'platformBranding' => [
                'appName' => $platformConfig['appName'] ?? 'TeamSuite',
                'appearanceTheme' => $user
                    ? $platformConfigService->resolveAppearanceTheme($club?->id)
                    : 'ocean-slate',
                'appLogoUrl' => $platformConfig['appLogoUrl'] ?? null,
                'healthCardTemplatesBySport' => is_array($platformConfig['healthCardTemplatesBySport'] ?? null)
                    ? $platformConfig['healthCardTemplatesBySport']
                    : [],
            ],
            'appVersion' => config('app.teamsuite_version', '3.0.0'),
            'flash' => [
                'backup_dry_run' => fn () => $request->session()->get('backup_dry_run'),
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
