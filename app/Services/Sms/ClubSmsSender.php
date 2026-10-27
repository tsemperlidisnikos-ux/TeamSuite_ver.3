<?php

namespace App\Services\Sms;

use App\Models\ClubSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ClubSmsSender
{
    /**
     * @param  array<string, mixed>|null  $sms
     */
    public function isConfigured(?array $sms): bool
    {
        if (! is_array($sms) || empty($sms['enabled'])) {
            return false;
        }

        $key = trim((string) ($sms['apiKey'] ?? ''));
        if ($key === '' || $key === '********') {
            return false;
        }

        $provider = $this->normalizeProvider((string) ($sms['provider'] ?? 'sms_to'));
        if ($provider === 'http') {
            return trim((string) ($sms['httpUrl'] ?? '')) !== '';
        }

        return true;
    }

    public function send(ClubSetting $settings, string $to, string $text): void
    {
        $sms = is_array($settings->sms) ? $settings->sms : null;
        if (! $this->isConfigured($sms)) {
            throw new RuntimeException('Ελλιπείς ρυθμίσεις SMS.');
        }

        $to = preg_replace('/\s+/', '', trim($to)) ?? '';
        $text = mb_substr(trim($text), 0, 480);
        if (strlen($to) < 8 || $text === '') {
            throw new RuntimeException('Ελλιπή στοιχεία μηνύματος.');
        }

        $apiKey = trim((string) ($sms['apiKey'] ?? ''));
        $sender = trim((string) ($sms['sender'] ?? '')) ?: 'TeamSuite';
        $provider = $this->normalizeProvider((string) ($sms['provider'] ?? 'sms_to'));

        if ($provider === 'http') {
            $url = trim((string) ($sms['httpUrl'] ?? ''));
            $res = Http::connectTimeout(5)
                ->timeout(20)
                ->withToken($apiKey)
                ->acceptJson()
                ->post($url, [
                    'to' => $to,
                    'message' => $text,
                    'text' => $text,
                    'sender_id' => $sender,
                ]);
            if (! $res->successful()) {
                throw new RuntimeException('HTTP SMS '.$res->status());
            }

            return;
        }

        $res = Http::connectTimeout(5)
            ->timeout(20)
            ->withToken($apiKey)
            ->acceptJson()
            ->post('https://api.sms.to/sms/send', [
                'to' => $to,
                'message' => $text,
                'sender_id' => mb_substr($sender, 0, 11),
            ]);

        if (! $res->successful()) {
            throw new RuntimeException((string) ($res->json('message') ?? ('SMS.to '.$res->status())));
        }
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = strtolower(trim($provider));

        return match ($provider) {
            'sms.to', 'smsto', 'sms_to' => 'sms_to',
            'http' => 'http',
            default => 'sms_to',
        };
    }
}
