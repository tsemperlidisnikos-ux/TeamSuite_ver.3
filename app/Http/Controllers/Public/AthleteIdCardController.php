<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformConfigService;
use Inertia\Inertia;
use Inertia\Response;

class AthleteIdCardController extends Controller
{
    /**
     * Public athlete ID card verifier (legacy `/id/card` with payload in URL hash).
     */
    public function __invoke(PlatformConfigService $config): Response
    {
        $platform = $config->get();

        return Inertia::render('Public/AthleteIdCard', [
            'appName' => $platform['appName'] ?? 'TeamSuite',
            'appLogoUrl' => $platform['appLogoUrl'] ?? null,
        ]);
    }
}
