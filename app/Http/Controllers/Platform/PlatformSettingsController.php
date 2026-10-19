<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformSettingsController extends Controller
{
    public function update(Request $request, PlatformConfigService $config): RedirectResponse
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $themeIds = $config->appearanceThemeIds();

        $data = $request->validate([
            'appName' => ['sometimes', 'nullable', 'string', 'max:120'],
            'appLogoUrl' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'appearanceTheme' => ['sometimes', 'string', Rule::in($themeIds)],
            'appearanceThemeByClub' => ['sometimes', 'array'],
            'appearanceThemeByClub.*' => ['nullable', 'string', Rule::in($themeIds)],
            'clubHeaderTitles' => ['sometimes', 'array'],
            'clubHeaderTitles.*' => ['nullable', 'string', 'max:120'],
            'financeTabs' => ['sometimes', 'array'],
            'financeTabs.*' => ['string'],
            'defaultAcademyModules' => ['sometimes', 'array'],
            'defaultAcademyModules.*' => ['string'],
            'academyModulesByClub' => ['sometimes', 'array'],
            'clubRolePermissions' => ['sometimes', 'array'],
            'incomeCategories' => ['sometimes', 'array'],
            'incomeCategories.*' => ['string', 'max:120'],
            'expenseCategories' => ['sometimes', 'array'],
            'expenseCategories.*' => ['string', 'max:120'],
            'incomeDescriptions' => ['sometimes', 'array'],
            'expenseDescriptions' => ['sometimes', 'array'],
            'seasons' => ['sometimes', 'array'],
            'seasons.*' => ['string', 'max:64'],
            'healthCardTemplatesBySport' => ['sometimes', 'array'],
        ]);

        $config->update($data);

        return back()->with('success', 'Οι ρυθμίσεις πλατφόρμας αποθηκεύτηκαν.');
    }
}
