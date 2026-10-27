<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Hosting\HostingCostService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlatformHostingController extends Controller
{
    public function update(Request $request, HostingCostService $hosting): RedirectResponse
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $data = $request->validate([
            'monthly_estimate_eur' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'prepaid_balance_eur' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'low_balance_threshold_eur' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'notify_email' => ['nullable', 'email', 'max:190'],
            'hetzner_api_token' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $hosting->updateBilling($data);

        return back()->with('success', 'Οι ρυθμίσεις κόστους hosting αποθηκεύτηκαν.');
    }
}
