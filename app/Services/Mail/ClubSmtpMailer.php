<?php

namespace App\Services\Mail;

use App\Models\ClubSetting;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class ClubSmtpMailer
{
    /**
     * Build an isolated SMTP mailer from the given club's settings only.
     * Does not use global MAIL_* / default mailer — another club never shares this config.
     *
     * @throws RuntimeException When SMTP is disabled or incomplete.
     */
    public function mailer(string $clubId): Mailer
    {
        $smtp = $this->smtpForClub($clubId);
        $config = $this->transportConfig($smtp);

        $mailer = Mail::build($config);
        $mailer->alwaysFrom(
            $config['from']['address'],
            $config['from']['name'],
        );

        return $mailer;
    }

    /**
     * @return array<string, mixed>
     */
    public function smtpForClub(string $clubId): array
    {
        $settings = ClubSetting::query()->where('club_id', $clubId)->first();
        $smtp = is_array($settings?->smtp) ? $settings->smtp : [];

        if (! filter_var($smtp['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('Το SMTP του συλλόγου δεν είναι ενεργό στις Ρυθμίσεις → Email.');
        }

        $host = trim((string) ($smtp['host'] ?? ''));
        $fromEmail = trim((string) ($smtp['fromEmail'] ?? ''));
        $username = trim((string) ($smtp['username'] ?? ''));
        $password = (string) ($smtp['password'] ?? '');
        $requireAuth = filter_var($smtp['requireAuth'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if ($host === '') {
            throw new RuntimeException('Λείπει το SMTP host στις Ρυθμίσεις → Email.');
        }

        if ($fromEmail === '' || ! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Λείπει έγκυρο From email στις Ρυθμίσεις → Email.');
        }

        if ($requireAuth && ($username === '' || $password === '')) {
            throw new RuntimeException('Λείπουν username/password SMTP στις Ρυθμίσεις → Email.');
        }

        return $smtp;
    }

    /**
     * @param  array<string, mixed>  $smtp
     * @return array<string, mixed>
     */
    public function transportConfig(array $smtp): array
    {
        $port = (int) ($smtp['port'] ?? 587);
        if ($port <= 0) {
            $port = 587;
        }

        $fromName = trim((string) ($smtp['fromName'] ?? ''));
        $username = trim((string) ($smtp['username'] ?? ''));
        $password = (string) ($smtp['password'] ?? '');

        return [
            'transport' => 'smtp',
            'host' => trim((string) ($smtp['host'] ?? '')),
            'port' => $port,
            'username' => $username !== '' ? $username : null,
            'password' => $password !== '' ? $password : null,
            'scheme' => $this->schemeFor($smtp),
            'timeout' => 30,
            'from' => [
                'address' => trim((string) ($smtp['fromEmail'] ?? '')),
                'name' => $fromName !== '' ? $fromName : (string) config('app.name'),
            ],
        ];
    }

    /**
     * @deprecated Use mailer() — kept for older call sites during transition.
     */
    public function configure(string $clubId): void
    {
        $this->mailer($clubId);
    }

    /**
     * @param  array<string, mixed>  $smtp
     */
    private function schemeFor(array $smtp): ?string
    {
        $security = strtolower(trim((string) ($smtp['security'] ?? 'starttls')));

        return match ($security) {
            'ssl', 'smtps', 'tls' => 'smtps',
            'none', 'off', 'plain' => null,
            default => null,
        };
    }
}
