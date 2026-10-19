<?php

namespace App\Jobs;

use App\Models\Athlete;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class HealthCardNoticeJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $soon = now()->addDays(30)->toDateString();
        $count = Athlete::query()->withoutGlobalScopes()
            ->whereNotNull('health_card_expires')
            ->whereDate('health_card_expires', '<=', $soon)
            ->where('status', 'active')
            ->count();

        Log::info('HealthCardNoticeJob: athletes with expiring health cards', ['count' => $count]);
    }
}
