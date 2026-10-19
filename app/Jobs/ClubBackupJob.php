<?php

namespace App\Jobs;

use App\Models\Club;
use App\Models\ClubBackup;
use App\Services\Backup\TeamSuiteClubExporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClubBackupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function handle(TeamSuiteClubExporter $exporter): void
    {
        Club::query()->where('is_active', true)->each(function (Club $club) use ($exporter) {
            try {
                $payload = $exporter->export($club);
                $filename = 'backups/'.$club->slug.'/'.now()->format('Y-m-d_H-i').'.json';
                Storage::disk('local')->put(
                    $filename,
                    json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                );

                ClubBackup::query()->create([
                    'id' => (string) Str::uuid(),
                    'club_id' => $club->id,
                    'scope' => 'club',
                    'source' => 'scheduled',
                    'storage_path' => $filename,
                    'exported_at' => now(),
                    'source_club_id' => $club->id,
                    'status' => 'exported',
                    'dry_run_counts' => [
                        'students' => count($payload['appData']['students'] ?? []),
                        'classes' => count($payload['appData']['classes'] ?? []),
                        'announcements' => count($payload['appData']['announcements'] ?? []),
                        'transactions' => count($payload['appData']['transactions'] ?? []),
                    ],
                ]);
            } catch (\Throwable $e) {
                report($e);
                Log::error('Scheduled club backup failed', [
                    'club_id' => $club->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
