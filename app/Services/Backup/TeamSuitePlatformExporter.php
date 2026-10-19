<?php

namespace App\Services\Backup;

use App\Models\Club;
use App\Models\User;
use App\Services\Platform\PlatformConfigService;

/**
 * Export all clubs (+ platformConfig) as legacy TeamSuite platform BackupPayload.
 */
class TeamSuitePlatformExporter
{
    public function __construct(
        private readonly TeamSuiteClubExporter $clubExporter,
        private readonly PlatformConfigService $platformConfig,
    ) {}

    public function prepareLongRunningExport(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(600);
        }
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '512M');
        }
    }

    /**
     * @return array{
     *     exportedAt: string,
     *     scope: string,
     *     sourceClubId: null,
     *     clubs: list<array<string, mixed>>,
     *     users: list<array<string, mixed>>,
     *     appData: array<string, mixed>,
     *     appDataByClub: array<string, array<string, mixed>>,
     *     platformConfig: array<string, mixed>
     * }
     */
    public function export(): array
    {
        $clubs = Club::query()
            ->with(['settings', 'licensePackage', 'users'])
            ->orderBy('name')
            ->get();

        $clubRecords = [];
        $appDataByClub = [];
        $usersByKey = [];

        foreach ($clubs as $club) {
            $payload = $this->clubExporter->export($club);
            $clubRecords[] = $payload['clubs'][0] ?? [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ];
            $appDataByClub[$club->id] = $payload['appData'] ?? [];

            foreach ($payload['users'] ?? [] as $userRow) {
                if (! is_array($userRow)) {
                    continue;
                }
                $key = ((string) ($userRow['id'] ?? '')).'|'.((string) ($userRow['clubId'] ?? ''));
                if ($key === '|') {
                    continue;
                }
                $usersByKey[$key] = $userRow;
            }
        }

        foreach ($this->exportPlatformAdmins() as $adminRow) {
            $key = ((string) $adminRow['id']).'|platform';
            $usersByKey[$key] = $adminRow;
        }

        $firstClubId = $clubRecords[0]['id'] ?? null;
        $firstAppData = $firstClubId && isset($appDataByClub[$firstClubId])
            ? $appDataByClub[$firstClubId]
            : [];

        return [
            'exportedAt' => now()->toIso8601String(),
            'scope' => 'platform',
            'sourceClubId' => null,
            'clubs' => $clubRecords,
            'users' => array_values($usersByKey),
            'appData' => $firstAppData,
            'appDataByClub' => $appDataByClub,
            'platformConfig' => $this->platformConfig->get(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportPlatformAdmins(): array
    {
        return User::query()
            ->where('is_platform_admin', true)
            ->orderBy('id')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'fullName' => $user->name,
                'email' => $user->email,
                'clubId' => null,
                'role' => 'platform_admin',
                'password' => '',
                'active' => true,
                'isPlatformAdmin' => true,
                'permissions' => [],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{clubs: int, users: int, students: int, classes: int, announcements: int, transactions: int}
     */
    public function summarize(array $payload): array
    {
        $students = 0;
        $classes = 0;
        $announcements = 0;
        $transactions = 0;

        foreach ($payload['appDataByClub'] ?? [] as $appData) {
            if (! is_array($appData)) {
                continue;
            }
            $students += is_array($appData['students'] ?? null) ? count($appData['students']) : 0;
            $classes += is_array($appData['classes'] ?? null) ? count($appData['classes']) : 0;
            $announcements += is_array($appData['announcements'] ?? null) ? count($appData['announcements']) : 0;
            $transactions += is_array($appData['transactions'] ?? null) ? count($appData['transactions']) : 0;
        }

        return [
            'clubs' => count($payload['clubs'] ?? []),
            'users' => count($payload['users'] ?? []),
            'students' => $students,
            'classes' => $classes,
            'announcements' => $announcements,
            'transactions' => $transactions,
        ];
    }
}
