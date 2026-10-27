<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\Club;
use App\Models\LicensePackage;
use App\Models\PlatformWaitlist;
use App\Services\Hosting\HostingCostService;
use App\Services\Platform\PlatformConfigService;
use App\Support\Navigation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, PlatformConfigService $config, HostingCostService $hosting): Response
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $clubs = Club::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'is_active'])
            ->map(fn (Club $club) => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
                'is_active' => (bool) $club->is_active,
                'athletes_count' => Athlete::query()->withoutGlobalScopes()
                    ->where('club_id', $club->id)
                    ->count(),
            ]);

        $previewId = $request->session()->get('platform_preview_club_id');
        $activePreview = $clubs->firstWhere('id', $previewId);

        return Inertia::render('Platform/Dashboard', [
            'clubsCount' => $clubs->count(),
            'packagesCount' => LicensePackage::query()->count(),
            'waitlistCount' => PlatformWaitlist::query()->count(),
            'clubs' => $clubs,
            'activePreview' => $activePreview
                ? ['id' => $activePreview['id'], 'name' => $activePreview['name']]
                : null,
            'platformConfig' => $this->configForInertia($config->get()),
            'hostingCost' => $hosting->snapshot(),
            'catalogs' => [
                'academyModules' => Navigation::academyModules(),
                'financeTabs' => $config->financeTabsCatalog(),
                'appearanceThemes' => $config->appearanceThemes(),
                'roleLabels' => $config->roleLabels(),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function configForInertia(array $config): array
    {
        if (isset($config['hostingBilling']) && is_array($config['hostingBilling'])) {
            $token = (string) ($config['hostingBilling']['hetzner_api_token'] ?? '');
            $config['hostingBilling']['hetzner_api_token'] = $token !== '' ? '********' : '';
        }

        return $config;
    }
}
