<?php

namespace App\Jobs;

use App\Models\AmkaAccessLog;
use App\Models\GdprAuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GdprRetentionJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        AmkaAccessLog::query()->where('created_at', '<', now()->subMonths(12))->delete();
        GdprAuditLog::query()->where('created_at', '<', now()->subYears(5))->delete();
    }
}
