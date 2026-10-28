<?php

namespace App\Services\Mail;

use App\Models\ClubSetting;
use Illuminate\Support\Facades\Config;
use RuntimeException;

class ClubSmtpMailer
{
    /**
     * Apply the club SMTP settings as the active Laravel mailer.
     *
     * @throws RuntimeException When SMTP is disabled or incomplete.
     */
    public function configure(string $clubId): void
    {
        $settings = ClubSetting::query()->where('club_id', $clubId)->first();
        $smtp = is_array($settings?->smtp) ? $settings->smtp : [];

        if (! filter_var($smtp['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('Το SMTP του συλλόγου δεν είναι ενεργό στις Ρυθμίσεις → Email.');
        }

        $host = trim((string) ($smtp['host'] ?? ''));
        $port = (int) ($smtp['port'] ?? 587);
        $username = trim((string) ($smtp['username'] ?? ''));
        $password = (string) ($smtp['password'] ?? '');
        $fromEmail = trim((string) ($smtp['fromEmail'] ?? ''));
        $fromName = trim((string) ($smtp['fromName'] ?? ''));
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

        if ($port <= 0) {
            $port = 587;
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', $port);
        Config::set('mail.mailers.smtp.username', $username !== '' ? $username : null);
        Config::set('mail.mailers.smtp.password', $password !== '' ? $password : null);
        Config::set('mail.mailers.smtp.scheme', $this->schemeFor($smtp));
        Config::set('mail.mailers.smtp.url', null);
        Config::set('mail.from.address', $fromEmail);
        Config::set('mail.from.name', $fromName !== '' ? $fromName : (string) config('app.name'));

        if (app()->bound('mail.manager')) {
            app('mail.manager')->purge('smtp');
        }
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
            default => null, // STARTTLS on 587: Symfony negotiates upgrade without smtps scheme
        };
    }
}
