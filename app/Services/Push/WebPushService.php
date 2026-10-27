<?php

namespace App\Services\Push;

use App\Models\ClubSetting;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lightweight Web Push sender without an extra Composer package.
 * Uses club_settings.push or env VAPID_* for the public key exposure;
 * delivery posts a minimal payload when the endpoint accepts unencrypted
 * demo traffic. Production clubs should set VAPID keys in env.
 */
class WebPushService
{
    public function publicKey(?ClubSetting $settings = null): ?string
    {
        $fromClub = is_array($settings?->push) ? trim((string) ($settings->push['vapidPublicKey'] ?? '')) : '';
        if ($fromClub !== '') {
            return $fromClub;
        }

        $fromEnv = trim((string) config('services.webpush.public_key', env('VAPID_PUBLIC_KEY', '')));

        return $fromEnv !== '' ? $fromEnv : null;
    }

    public function isEnabled(?ClubSetting $settings = null): bool
    {
        if (is_array($settings?->push) && ! empty($settings->push['enabled'])) {
            return $this->publicKey($settings) !== null;
        }

        return $this->publicKey($settings) !== null;
    }

    /**
     * @param  list<PushSubscription>  $subscriptions
     * @return array{sent: int, failed: int}
     */
    public function sendToSubscriptions(iterable $subscriptions, string $title, string $body, ?string $url = null): array
    {
        $sent = 0;
        $failed = 0;
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ], JSON_UNESCAPED_UNICODE);

        foreach ($subscriptions as $subscription) {
            if (! $subscription instanceof PushSubscription) {
                continue;
            }

            try {
                $res = Http::connectTimeout(3)
                    ->timeout(10)
                    ->withHeaders([
                        'TTL' => '60',
                        'Content-Type' => 'application/json',
                    ])
                    ->withBody($payload ?: '{}', 'application/json')
                    ->post($subscription->endpoint);

                if ($res->successful() || in_array($res->status(), [201, 204], true)) {
                    $sent++;
                } else {
                    $failed++;
                    if (in_array($res->status(), [404, 410], true)) {
                        $subscription->delete();
                    }
                    Log::info('Web push endpoint rejected payload', [
                        'status' => $res->status(),
                        'subscription_id' => $subscription->id,
                    ]);
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Web push send failed', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }
}
