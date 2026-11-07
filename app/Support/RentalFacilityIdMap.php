<?php

namespace App\Support;

use App\Models\Facility;
use Illuminate\Support\Collection;

final class RentalFacilityIdMap
{
    /**
     * Map legacy TeamSuite facility ids (facility_…) to current UUID primary keys.
     *
     * @return array<string, string> legacy_id => uuid
     */
    public static function legacyToUuid(string $clubId): array
    {
        return Facility::query()
            ->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->whereNotNull('legacy_id')
            ->pluck('id', 'legacy_id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    /**
     * Rewrite rule facilityId values from legacy ids to UUIDs when needed.
     *
     * @param  list<array<string, mixed>>  $rules
     * @param  array<string, string>|Collection<string, string>  $legacyToUuid
     * @return list<array<string, mixed>>
     */
    public static function remapRules(array $rules, array|Collection $legacyToUuid): array
    {
        $map = $legacyToUuid instanceof Collection ? $legacyToUuid->all() : $legacyToUuid;
        $out = [];

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $facilityId = (string) ($rule['facilityId'] ?? $rule['facility_id'] ?? '');
            if ($facilityId !== '' && isset($map[$facilityId])) {
                $facilityId = (string) $map[$facilityId];
            }

            if ($facilityId === '') {
                continue;
            }

            $rule['facilityId'] = $facilityId;
            unset($rule['facility_id']);
            $out[] = $rule;
        }

        return $out;
    }
}
