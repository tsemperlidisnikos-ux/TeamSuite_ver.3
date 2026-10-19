<?php

namespace App\Services\Backup;

use App\Models\Club;
use App\Models\ClubBackup;
use App\Models\ClubSetting;
use App\Models\LicensePackage;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Orchestrates legacy TeamSuite BackupPayload restore.
 * AppData ETL is delegated to TeamSuiteAppDataImporter.
 */
class TeamSuiteBackupRestoreService
{
    public function __construct(
        private readonly TeamSuiteBackupParser $parser,
        private readonly TeamSuiteAppDataImporter $importer,
    ) {}

    /**
     * @return array{backup: ClubBackup, counts: array<string, int>, clubExists: bool, parsed: array<string, mixed>}
     */
    public function dryRun(string $json, ?User $uploader = null): array
    {
        $parsed = $this->parser->parse($json);
        $sourceClubId = $parsed['sourceClubId']
            ?? ($parsed['clubs'][0]['id'] ?? array_key_first($parsed['appDataByClub']));

        $appData = $sourceClubId && isset($parsed['appDataByClub'][$sourceClubId])
            ? $parsed['appDataByClub'][$sourceClubId]
            : (array_values($parsed['appDataByClub'])[0] ?? []);

        if (($parsed['scope'] ?? '') === 'platform' && count($parsed['appDataByClub'] ?? []) > 1) {
            $counts = $this->aggregatePlatformCounts($parsed);
        } else {
            $counts = $this->parser->countAppData($appData);
            $counts['users'] = count($parsed['users']);
            $counts['clubs'] = count($parsed['clubs']);
        }

        $clubExists = $sourceClubId
            ? Club::withTrashed()->where('id', $sourceClubId)->exists()
            : false;

        $backup = ClubBackup::query()->create([
            'club_id' => $clubExists ? $sourceClubId : null,
            'scope' => $parsed['scope'],
            'source' => 'upload',
            'exported_at' => $parsed['exportedAt'] ?? null,
            'source_club_id' => $sourceClubId,
            'dry_run_counts' => $counts,
            'status' => 'dry_run',
            'uploaded_by' => $uploader?->id,
        ]);

        return compact('backup', 'counts', 'clubExists') + ['parsed' => $parsed];
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    public function ensureClubFromPayload(array $parsed): Club
    {
        $clubPayload = $parsed['clubs'][0] ?? null;
        $sourceClubId = $parsed['sourceClubId'] ?? ($clubPayload['id'] ?? null);

        if (! $sourceClubId) {
            throw new \InvalidArgumentException('Λείπει sourceClubId από το backup.');
        }

        return DB::transaction(function () use ($sourceClubId, $clubPayload) {
            $club = Club::withTrashed()->find($sourceClubId);

            if (! $club) {
                $name = $clubPayload['name'] ?? 'Εισαγόμενος σύλλογος';
                $slug = $clubPayload['slug'] ?? Str::slug($name).'-'.Str::lower(Str::random(4));

                $club = Club::query()->create([
                    'id' => $sourceClubId,
                    'name' => $name,
                    'slug' => $slug,
                    'is_active' => true,
                ]);

                ClubSetting::query()->create([
                    'club_id' => $club->id,
                    'smtp' => $this->sanitizeSecrets($clubPayload['smtp'] ?? null),
                    'sms' => $this->sanitizeSecrets($clubPayload['sms'] ?? null),
                    'viva' => $this->sanitizeSecrets($clubPayload['viva'] ?? null),
                    'stripe' => $this->sanitizeSecrets($clubPayload['stripe'] ?? null),
                    'eurobank' => $this->sanitizeSecrets($clubPayload['eurobank'] ?? null),
                    'profile' => $this->profileFromClubPayload($clubPayload),
                    'public_registration' => is_array($clubPayload['publicRegistration'] ?? null)
                        ? $clubPayload['publicRegistration']
                        : null,
                    'public_join_enabled' => (bool) ($clubPayload['publicRegistration']['enabled'] ?? true),
                    'dpa_accepted_at' => $clubPayload['dpaAcceptedAt'] ?? null,
                ]);
            } else {
                if ($club->trashed()) {
                    $club->restore();
                }
                if ($clubPayload) {
                    $club->fill([
                        'name' => $clubPayload['name'] ?? $club->name,
                        'slug' => $clubPayload['slug'] ?? $club->slug,
                    ])->save();
                    $this->mergeSettingsPreservingSecrets($club, $clubPayload);
                }
            }

            if (is_array($clubPayload)) {
                $this->syncLicensePackageFromPayload($club, $clubPayload);
            }

            return $club->fresh(['settings', 'licensePackage']);
        });
    }

    /**
     * Legacy backups store athleteLicenseLimit on the club object.
     * Map it to license_packages.athlete_cap and assign the package to the club.
     *
     * @param  array<string, mixed>  $clubPayload
     */
    private function syncLicensePackageFromPayload(Club $club, array $clubPayload): void
    {
        $limit = $clubPayload['athleteLicenseLimit'] ?? $clubPayload['athlete_license_limit'] ?? null;
        if ($limit === null || $limit === '' || (int) $limit <= 0) {
            return;
        }

        $cap = (int) $limit;
        $packageName = (string) ($clubPayload['name'] ?? $club->name);

        $package = LicensePackage::query()
            ->where('name', $packageName)
            ->where('athlete_cap', $cap)
            ->first();

        if (! $package) {
            $package = LicensePackage::query()
                ->where('athlete_cap', $cap)
                ->where('name', 'like', $packageName.'%')
                ->first();
        }

        if (! $package) {
            $package = LicensePackage::query()->create([
                'id' => (string) Str::uuid(),
                'name' => $packageName,
                'athlete_cap' => $cap,
                'modules' => ['*'],
                'is_active' => true,
            ]);
        } elseif ((int) $package->athlete_cap !== $cap) {
            $package->forceFill(['athlete_cap' => $cap])->save();
        }

        if ($club->license_package_id !== $package->id) {
            $club->forceFill(['license_package_id' => $package->id])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, int>
     */
    public function restoreClub(array $parsed, string $mode = 'merge', ?ClubBackup $backup = null): array
    {
        $this->prepareLongRunningRestore();

        $club = $this->ensureClubFromPayload($parsed);
        $appData = $parsed['appDataByClub'][$club->id]
            ?? array_values($parsed['appDataByClub'])[0]
            ?? [];

        $usersForClub = $this->usersForClub($parsed['users'] ?? [], $club);
        $userMap = $this->importUsers($usersForClub, $club);
        $this->importer->setUserIdMap($userMap);

        $imported = $this->importer->import($club->id, $appData, $mode);
        $imported['users'] = count($userMap);

        if ($backup) {
            $backup->update([
                'club_id' => $club->id,
                'status' => 'restored',
                'dry_run_counts' => $imported,
            ]);
        }

        return $imported;
    }

    /**
     * Legacy backups include club users (parents/coaches/staff) with blank passwords.
     * Create or attach them so parentLinks and portal roles resolve.
     *
     * @param  list<array<string, mixed>>  $users
     * @return array<string, int> legacy user id → users.id
     */
    public function importUsers(array $users, Club $club): array
    {
        $map = [];
        $allowedRoles = Roles::clubRoles();

        foreach ($users as $row) {
            if (! is_array($row)) {
                continue;
            }

            $legacyId = isset($row['id']) ? (string) $row['id'] : '';
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if ($legacyId === '' || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $name = trim((string) ($row['fullName'] ?? $row['name'] ?? ''));
            if ($name === '') {
                $name = $email;
            }

            $role = strtolower(trim((string) ($row['role'] ?? Roles::STAFF)));
            if (! in_array($role, $allowedRoles, true)) {
                $role = Roles::STAFF;
            }

            $permissions = $row['permissions'] ?? [];
            if (! is_array($permissions)) {
                $permissions = [];
            }

            $user = User::query()->where('email', $email)->first();
            if (! $user) {
                $plainPassword = (string) ($row['password'] ?? '');
                if ($plainPassword === '' || $this->parser->isBlankOrMaskedSecret($plainPassword)) {
                    $plainPassword = Str::password(32);
                }

                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($plainPassword),
                    'email_verified_at' => now(),
                    'is_platform_admin' => false,
                    'active_club_id' => $club->id,
                ]);
            } else {
                $user->forceFill([
                    'name' => $name !== $email ? $name : $user->name,
                    'active_club_id' => $user->active_club_id ?: $club->id,
                ])->save();
            }

            $user->clubs()->syncWithoutDetaching([
                $club->id => [
                    'role' => $role,
                    'permissions' => json_encode(array_values($permissions)),
                    'finance_own_entries_only' => false,
                    'is_active' => (bool) ($row['active'] ?? true),
                ],
            ]);

            $map[$legacyId] = (int) $user->id;
        }

        return $map;
    }

    /**
     * After restore, make the restored club the active context for the operator.
     */
    public function activateClubForUser(?User $user, Club $club): void
    {
        if (! $user) {
            return;
        }

        $user->clubs()->syncWithoutDetaching([
            $club->id => [
                'role' => 'admin',
                'permissions' => json_encode([]),
                'finance_own_entries_only' => false,
                'is_active' => true,
            ],
        ]);

        $user->forceFill(['active_club_id' => $club->id])->save();
    }

    /**
     * Large TeamSuite JSON restores routinely exceed PHP's default 30s limit.
     */
    public function prepareLongRunningRestore(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '1024M');
        ignore_user_abort(true);

        try {
            DB::connection()->disableQueryLog();
        } catch (\Throwable) {
            // Connection may not support query log (e.g. during early boot).
        }
    }

    /**
     * Platform backups list every club membership; restore only those for the target club.
     * Rows without clubId are kept for legacy single-club payloads.
     *
     * @param  list<array<string, mixed>>  $users
     * @return list<array<string, mixed>>
     */
    private function usersForClub(array $users, Club $club): array
    {
        $clubId = (string) $club->id;

        return array_values(array_filter($users, function ($row) use ($clubId) {
            if (! is_array($row)) {
                return false;
            }
            if (! empty($row['isPlatformAdmin']) || ($row['role'] ?? '') === 'platform_admin') {
                return false;
            }
            $rowClubId = $row['clubId'] ?? null;
            if ($rowClubId === null || $rowClubId === '') {
                return true;
            }

            return (string) $rowClubId === $clubId;
        }));
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, int>
     */
    private function aggregatePlatformCounts(array $parsed): array
    {
        $totals = [];
        foreach ($parsed['appDataByClub'] ?? [] as $appData) {
            if (! is_array($appData)) {
                continue;
            }
            foreach ($this->parser->countAppData($appData) as $key => $count) {
                $totals[$key] = ($totals[$key] ?? 0) + $count;
            }
        }
        $totals['users'] = count($parsed['users'] ?? []);
        $totals['clubs'] = count($parsed['clubs'] ?? []);

        return $totals;
    }

    /** @param  array<string, mixed>|null  $incoming */
    private function sanitizeSecrets(?array $incoming): ?array
    {
        if ($incoming === null) {
            return null;
        }
        foreach (['password', 'apiKey', 'clientSecret', 'secretKey'] as $secretKey) {
            if (isset($incoming[$secretKey]) && $this->parser->isBlankOrMaskedSecret((string) $incoming[$secretKey])) {
                unset($incoming[$secretKey]);
            }
        }

        return $incoming;
    }

    /** @param  array<string, mixed>  $clubPayload */
    private function mergeSettingsPreservingSecrets(Club $club, array $clubPayload): void
    {
        $settings = $club->settings()->firstOrCreate(['club_id' => $club->id]);
        foreach (['smtp', 'sms', 'viva', 'stripe', 'eurobank'] as $channel) {
            if (! isset($clubPayload[$channel]) || ! is_array($clubPayload[$channel])) {
                continue;
            }
            $existing = $settings->{$channel} ?? [];
            $incoming = $clubPayload[$channel];
            foreach (['password', 'apiKey', 'clientSecret', 'secretKey'] as $secretKey) {
                if (
                    array_key_exists($secretKey, $incoming)
                    && $this->parser->isBlankOrMaskedSecret(
                        is_string($incoming[$secretKey] ?? null) ? $incoming[$secretKey] : null
                    )
                ) {
                    $incoming[$secretKey] = $existing[$secretKey] ?? '';
                }
            }
            $settings->{$channel} = array_merge($existing, $incoming);
        }

        $profile = $this->profileFromClubPayload($clubPayload);
        if ($profile !== null) {
            $settings->profile = array_merge($settings->profile ?? [], $profile);
        }
        if (isset($clubPayload['publicRegistration']) && is_array($clubPayload['publicRegistration'])) {
            $settings->public_registration = array_merge(
                $settings->public_registration ?? [],
                $clubPayload['publicRegistration']
            );
            $settings->public_join_enabled = (bool) ($clubPayload['publicRegistration']['enabled']
                ?? $settings->public_join_enabled);
        }
        if (! empty($clubPayload['dpaAcceptedAt'])) {
            $settings->dpa_accepted_at = $clubPayload['dpaAcceptedAt'];
        }

        $settings->save();
    }

    /** @param  array<string, mixed>  $clubPayload */
    private function profileFromClubPayload(array $clubPayload): ?array
    {
        $map = [
            'vatNumber' => 'vat_number',
            'taxOffice' => 'tax_office',
            'address' => 'address',
            'city' => 'city',
            'foundedYear' => 'founded_year',
            'website' => 'website',
            'phone' => 'phone',
            'email' => 'email',
            'customChargeLabel' => 'custom_charge_label',
        ];
        $profile = [];
        foreach ($map as $from => $to) {
            if (array_key_exists($from, $clubPayload) && $clubPayload[$from] !== null && $clubPayload[$from] !== '') {
                $profile[$to] = $clubPayload[$from];
            }
        }

        return $profile === [] ? null : $profile;
    }
}
