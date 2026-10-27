<?php

namespace App\Jobs;

use App\Mail\HostingBalanceAlertMail;
use App\Models\User;
use App\Services\Hosting\HostingCostService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckHostingBalanceJob implements ShouldQueue
{
    use Queueable;

    public function handle(HostingCostService $hosting): void
    {
        $snap = $hosting->snapshot();
        if (! $snap['needs_topup']) {
            return;
        }

        $last = $snap['last_notified_at'] ?? null;
        if (is_string($last) && $last !== '' && now()->diffInHours($last) < 48) {
            return;
        }

        $email = $snap['notify_email']
            ?: User::query()->where('is_platform_admin', true)->orderBy('id')->value('email');

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Hosting balance low but no notify email configured', $snap);

            return;
        }

        try {
            Mail::to($email)->send(new HostingBalanceAlertMail($snap));
            $hosting->markNotified();
        } catch (\Throwable $e) {
            Log::warning('Hosting balance notify failed', ['error' => $e->getMessage()]);
        }
    }
}
