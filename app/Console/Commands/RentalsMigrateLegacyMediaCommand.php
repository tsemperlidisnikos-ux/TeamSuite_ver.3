<?php

namespace App\Console\Commands;

use App\Models\Facility;
use App\Models\RentalSetting;
use App\Support\ClubPublicMedia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RentalsMigrateLegacyMediaCommand extends Command
{
    protected $signature = 'rentals:migrate-legacy-media
                            {--club= : Optional club id}
                            {--dry-run : List paths without writing}';

    protected $description = 'Download legacy TeamSuite /api/club-media rental photos into local public storage';

    public function handle(): int
    {
        $clubId = $this->option('club');
        $dryRun = (bool) $this->option('dry-run');
        $base = rtrim((string) config('services.teamsuite_legacy.media_base_url', ''), '/');

        if ($base === '') {
            $this->error('TEAMSUITE_LEGACY_MEDIA_BASE_URL is not configured.');

            return self::FAILURE;
        }

        $migrated = 0;
        $skipped = 0;
        $failed = 0;

        $facilities = Facility::query()
            ->withoutGlobalScopes()
            ->when(is_string($clubId) && $clubId !== '', fn ($q) => $q->where('club_id', $clubId))
            ->get(['id', 'club_id', 'name', 'meta']);

        foreach ($facilities as $facility) {
            $meta = is_array($facility->meta) ? $facility->meta : [];
            $raw = isset($meta['photoUrl'])
                ? (string) $meta['photoUrl']
                : (isset($meta['photo_url']) ? (string) $meta['photo_url'] : '');

            if ($raw === '' || ! ClubPublicMedia::isLegacyClubMediaPath($raw)) {
                $skipped++;

                continue;
            }

            $result = $this->migratePath($raw, (string) $facility->club_id, 'club-media/facilities', $base, $dryRun);
            if ($result === null) {
                $failed++;
                $this->warn("Facility {$facility->name}: failed to migrate");

                continue;
            }

            if (! $dryRun) {
                $meta['photoUrl'] = $result;
                unset($meta['photo_url']);
                $facility->meta = $meta;
                $facility->save();
            }

            $migrated++;
            $this->line(($dryRun ? '[dry-run] ' : '')."Facility {$facility->name} → {$result}");
        }

        $settings = RentalSetting::query()
            ->withoutGlobalScopes()
            ->when(is_string($clubId) && $clubId !== '', fn ($q) => $q->where('club_id', $clubId))
            ->get(['id', 'club_id', 'rules']);

        foreach ($settings as $setting) {
            $rules = is_array($setting->rules) ? $setting->rules : [];
            if (array_is_list($rules)) {
                $rules = ['rules' => $rules, 'notes' => '', 'heroImageUrl' => null];
            }

            $hero = isset($rules['heroImageUrl']) ? (string) $rules['heroImageUrl'] : '';
            if ($hero === '' || ! ClubPublicMedia::isLegacyClubMediaPath($hero)) {
                continue;
            }

            $result = $this->migratePath($hero, (string) $setting->club_id, 'club-media/rent-hero', $base, $dryRun);
            if ($result === null) {
                $failed++;
                $this->warn("Hero club {$setting->club_id}: failed to migrate");

                continue;
            }

            if (! $dryRun) {
                $rules['heroImageUrl'] = $result;
                $setting->rules = $rules;
                $setting->save();
            }

            $migrated++;
            $this->line(($dryRun ? '[dry-run] ' : '')."Hero {$setting->club_id} → {$result}");
        }

        $this->info("Done. migrated={$migrated} skipped={$skipped} failed={$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function migratePath(string $raw, string $clubId, string $prefix, string $base, bool $dryRun): ?string
    {
        $source = str_starts_with($raw, 'http')
            ? $raw
            : $base.(str_starts_with($raw, '/') ? $raw : '/'.$raw);

        if ($dryRun) {
            return $prefix.'/'.$clubId.'/legacy-'.Str::uuid().'.jpg';
        }

        $response = Http::timeout(60)->get($source);
        if (! $response->successful()) {
            return null;
        }

        $bytes = $response->body();
        if ($bytes === '') {
            return null;
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        $extension = match (true) {
            str_contains($contentType, 'png') => 'png',
            str_contains($contentType, 'webp') => 'webp',
            str_contains($contentType, 'gif') => 'gif',
            default => 'jpg',
        };

        $path = trim($prefix, '/').'/'.$clubId.'/'.Str::uuid()->toString().'.'.$extension;
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }
}
