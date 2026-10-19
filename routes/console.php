<?php

use App\Jobs\ClubBackupJob;
use App\Jobs\GdprRetentionJob;
use App\Jobs\GenerateFeeChargesJob;
use App\Jobs\HealthCardNoticeJob;
use App\Jobs\WarehouseLowStockJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new GenerateFeeChargesJob)->dailyAt('06:00');
Schedule::job(new HealthCardNoticeJob)->dailyAt('07:00');
Schedule::job(new WarehouseLowStockJob)->dailyAt('08:00');
Schedule::job(new GdprRetentionJob)->dailyAt('03:30');
Schedule::job(new ClubBackupJob)->dailyAt('02:00')->withoutOverlapping(120);
