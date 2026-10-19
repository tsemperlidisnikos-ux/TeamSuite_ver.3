<?php

namespace App\Services\Backup;

use InvalidArgumentException;

/**
 * Parses legacy TeamSuite BackupPayload JSON (club or platform scope).
 * Secrets that are blank/masked must NOT overwrite existing secrets on restore.
 */
class TeamSuiteBackupParser
{
    public const MASKED = '********';

    /**
     * @return array{
     *   exportedAt: ?string,
     *   scope: string,
     *   sourceClubId: ?string,
     *   clubs: list<array<string, mixed>>,
     *   users: list<array<string, mixed>>,
     *   appDataByClub: array<string, array<string, mixed>>,
     *   platformConfig: ?array<string, mixed>
     * }
     */
    public function parse(string $json): array
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Μη έγκυρο JSON backup.');
        }

        if (
            empty($decoded['appData'])
            && empty($decoded['appDataByClub'])
            && empty($decoded['platformConfig'])
            && empty($decoded['users'])
            && empty($decoded['clubs'])
        ) {
            throw new InvalidArgumentException('Το αρχείο δεν μοιάζει με TeamSuite BackupPayload.');
        }

        $scope = $decoded['scope'] ?? (isset($decoded['appDataByClub']) && count($decoded['appDataByClub'] ?? []) > 1
            ? 'platform'
            : 'club');

        $appDataByClub = $decoded['appDataByClub'] ?? [];
        if ($appDataByClub === [] && isset($decoded['appData']) && is_array($decoded['appData'])) {
            $clubId = $decoded['sourceClubId'] ?? ($decoded['clubs'][0]['id'] ?? 'unknown');
            $appDataByClub = [(string) $clubId => $decoded['appData']];
        }

        return [
            'exportedAt' => $decoded['exportedAt'] ?? null,
            'scope' => $scope,
            'sourceClubId' => isset($decoded['sourceClubId']) ? (string) $decoded['sourceClubId'] : null,
            'clubs' => array_values($decoded['clubs'] ?? []),
            'users' => array_values($decoded['users'] ?? []),
            'appDataByClub' => $appDataByClub,
            'platformConfig' => $decoded['platformConfig'] ?? null,
        ];
    }

    /**
     * Dry-run counters for UI confirmation before replace/merge.
     *
     * @param  array<string, mixed>  $appData
     * @return array<string, int>
     */
    public function countAppData(array $appData): array
    {
        $keys = [
            'students', 'coaches', 'staff', 'clubMembers', 'classes', 'schedule',
            'trainings', 'attendance', 'matches', 'transactions', 'revenues', 'expenses',
            'products', 'announcements', 'photos', 'registrationApplications',
            'parentLinks', 'facilities', 'rentalBookings', 'documentProtocolEntries',
        ];

        $counts = [];
        foreach ($keys as $key) {
            $counts[$key] = isset($appData[$key]) && is_array($appData[$key])
                ? count($appData[$key])
                : 0;
        }

        return $counts;
    }

    public function isBlankOrMaskedSecret(?string $value): bool
    {
        if ($value === null) {
            return true;
        }

        $trimmed = trim($value);

        return $trimmed === '' || $trimmed === self::MASKED;
    }
}
