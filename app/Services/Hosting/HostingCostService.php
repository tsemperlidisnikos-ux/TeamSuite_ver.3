<?php

namespace App\Services\Hosting;

use App\Services\Platform\PlatformConfigService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HostingCostService
{
    public function __construct(
        private readonly PlatformConfigService $platformConfig,
    ) {}

    /**
     * @return array{
     *   provider: string,
     *   monthly_estimate_eur: float,
     *   prepaid_balance_eur: ?float,
     *   low_balance_threshold_eur: float,
     *   notify_email: ?string,
     *   hetzner_api_token_set: bool,
     *   notes: string,
     *   last_topup_at: ?string,
     *   last_notified_at: ?string,
     *   estimated_from_api: bool,
     *   api_servers: list<array{name: string, type: string, monthly_eur: float}>,
     *   remaining_eur: ?float,
     *   days_remaining: ?float,
     *   needs_topup: bool,
     *   status: string,
     *   status_label: string
     * }
     */
    public function snapshot(): array
    {
        $billing = $this->billingConfig();
        $api = $this->estimateFromHetznerApi($billing['hetzner_api_token'] ?? '');

        $monthly = $api['monthly'] > 0
            ? $api['monthly']
            : (float) ($billing['monthly_estimate_eur'] ?? 7.5);

        $prepaid = $billing['prepaid_balance_eur'];
        $threshold = (float) ($billing['low_balance_threshold_eur'] ?? 15);
        $remaining = is_numeric($prepaid) ? (float) $prepaid : null;
        $daily = $monthly > 0 ? $monthly / 30 : 0;
        $daysRemaining = ($remaining !== null && $daily > 0) ? round($remaining / $daily, 1) : null;
        $needsTopup = $remaining !== null && ($remaining <= $threshold || ($daysRemaining !== null && $daysRemaining <= 14));

        $status = 'ok';
        $statusLabel = 'Επαρκές υπόλοιπο / κανονική λειτουργία';
        if ($remaining === null) {
            $status = 'unknown';
            $statusLabel = 'Δεν έχει καταχωρηθεί υπόλοιπο Hetzner — συμπλήρωσέ το μετά από έλεγχο στο console';
        } elseif ($needsTopup) {
            $status = 'low';
            $statusLabel = 'Χαμηλό υπόλοιπο — πρόσθεσε χρήματα στο Hetzner';
        }

        return [
            'provider' => (string) ($billing['provider'] ?? 'hetzner'),
            'monthly_estimate_eur' => round($monthly, 2),
            'prepaid_balance_eur' => $remaining,
            'low_balance_threshold_eur' => $threshold,
            'notify_email' => $billing['notify_email'] ?: null,
            'hetzner_api_token_set' => trim((string) ($billing['hetzner_api_token'] ?? '')) !== '',
            'notes' => (string) ($billing['notes'] ?? ''),
            'last_topup_at' => $billing['last_topup_at'] ?? null,
            'last_notified_at' => $billing['last_notified_at'] ?? null,
            'estimated_from_api' => $api['monthly'] > 0,
            'api_servers' => $api['servers'],
            'remaining_eur' => $remaining,
            'days_remaining' => $daysRemaining,
            'needs_topup' => $needsTopup,
            'status' => $status,
            'status_label' => $statusLabel,
        ];
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    public function updateBilling(array $patch): array
    {
        $current = $this->billingConfig();
        $token = $patch['hetzner_api_token'] ?? null;
        if (is_string($token) && ($token === '' || $token === '********')) {
            unset($patch['hetzner_api_token']);
        }

        $next = array_merge($current, array_filter(
            $patch,
            fn ($value, $key) => $key !== 'hetzner_api_token' || (is_string($value) && $value !== '' && $value !== '********'),
            ARRAY_FILTER_USE_BOTH,
        ));

        if (array_key_exists('hetzner_api_token', $patch)
            && is_string($patch['hetzner_api_token'])
            && $patch['hetzner_api_token'] !== ''
            && $patch['hetzner_api_token'] !== '********') {
            $next['hetzner_api_token'] = $patch['hetzner_api_token'];
        }

        if (array_key_exists('prepaid_balance_eur', $patch) && is_numeric($patch['prepaid_balance_eur'])) {
            $next['last_topup_at'] = now()->toIso8601String();
        }

        $this->platformConfig->update(['hostingBilling' => $next]);

        return $this->snapshot();
    }

    public function markNotified(): void
    {
        $billing = $this->billingConfig();
        $billing['last_notified_at'] = now()->toIso8601String();
        $this->platformConfig->update(['hostingBilling' => $billing]);
    }

    /** @return array<string, mixed> */
    private function billingConfig(): array
    {
        $config = $this->platformConfig->get();
        $billing = is_array($config['hostingBilling'] ?? null) ? $config['hostingBilling'] : [];

        return array_merge($this->platformConfig->defaults()['hostingBilling'], $billing);
    }

    /**
     * @return array{monthly: float, servers: list<array{name: string, type: string, monthly_eur: float}>}
     */
    private function estimateFromHetznerApi(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            return ['monthly' => 0.0, 'servers' => []];
        }

        try {
            $serversRes = Http::connectTimeout(5)
                ->timeout(15)
                ->withToken($token)
                ->acceptJson()
                ->get('https://api.hetzner.cloud/v1/servers');

            $pricingRes = Http::connectTimeout(5)
                ->timeout(15)
                ->withToken($token)
                ->acceptJson()
                ->get('https://api.hetzner.cloud/v1/pricing');

            if (! $serversRes->successful() || ! $pricingRes->successful()) {
                return ['monthly' => 0.0, 'servers' => []];
            }

            $priceByType = [];
            foreach ($pricingRes->json('pricing.server_types') ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $name = (string) ($row['name'] ?? '');
                $prices = $row['prices'][0]['price_monthly']['gross']
                    ?? $row['prices'][0]['price_monthly']['net']
                    ?? null;
                if ($name !== '' && is_numeric($prices)) {
                    $priceByType[$name] = (float) $prices;
                }
            }

            $servers = [];
            $total = 0.0;
            foreach ($serversRes->json('servers') ?? [] as $server) {
                if (! is_array($server)) {
                    continue;
                }
                $type = (string) ($server['server_type']['name'] ?? 'unknown');
                $monthly = $priceByType[$type] ?? 0.0;
                $total += $monthly;
                $servers[] = [
                    'name' => (string) ($server['name'] ?? 'server'),
                    'type' => $type,
                    'monthly_eur' => round($monthly, 2),
                ];
            }

            // Typical primary IPv4 ~€0.50
            if ($servers !== []) {
                $total += 0.5 * count($servers);
            }

            return ['monthly' => round($total, 2), 'servers' => $servers];
        } catch (\Throwable $e) {
            Log::warning('Hetzner cost estimate failed', ['error' => $e->getMessage()]);

            return ['monthly' => 0.0, 'servers' => []];
        }
    }
}
