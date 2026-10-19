<?php

namespace App\Jobs;

use App\Models\Athlete;
use App\Models\AthleteTransaction;
use App\Models\Club;
use App\Models\FeeChargeTemplate;
use App\Models\SuppressedFeeChargeKey;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class GenerateFeeChargesJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $month = (int) now()->month;
        $year = (int) now()->year;

        Club::query()->where('is_active', true)->each(function (Club $club) use ($month, $year) {
            $templates = FeeChargeTemplate::query()->withoutGlobalScopes()
                ->where('club_id', $club->id)
                ->where('auto_generate', true)
                ->where('is_active', true)
                ->get();

            foreach ($templates as $template) {
                Athlete::query()->withoutGlobalScopes()
                    ->where('club_id', $club->id)
                    ->where('status', 'active')
                    ->each(function (Athlete $athlete) use ($club, $template, $month, $year) {
                        $key = "{$athlete->id}:{$template->id}:{$year}-{$month}";
                        $suppressed = SuppressedFeeChargeKey::query()
                            ->where('club_id', $club->id)
                            ->where('suppress_key', $key)
                            ->exists();
                        if ($suppressed) {
                            return;
                        }

                        $exists = AthleteTransaction::query()->withoutGlobalScopes()
                            ->where('club_id', $club->id)
                            ->where('athlete_id', $athlete->id)
                            ->where('type', 'charge')
                            ->where('month', $month)
                            ->where('year', $year)
                            ->where('meta->template_id', $template->id)
                            ->exists();

                        if ($exists) {
                            return;
                        }

                        AthleteTransaction::query()->withoutGlobalScopes()->create([
                            'id' => (string) Str::uuid(),
                            'club_id' => $club->id,
                            'athlete_id' => $athlete->id,
                            'type' => 'charge',
                            'amount' => $template->amount,
                            'month' => $month,
                            'year' => $year,
                            'comments' => $template->name,
                            'meta' => ['template_id' => $template->id, 'auto' => true],
                        ]);
                    });
            }
        });
    }
}
