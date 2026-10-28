<?php

namespace App\Support;

use App\Models\ClothingPackage;
use Illuminate\Support\Str;

class ClothingPackageIds
{
    /**
     * Map stored clothing package refs (UUID or legacy_id) to current club UUIDs.
     *
     * @param  list<mixed>  $ids
     * @return list<string>
     */
    public static function resolve(array $ids, string $clubId): array
    {
        $raw = collect($ids)
            ->map(fn ($id) => is_string($id) ? trim($id) : '')
            ->filter()
            ->unique()
            ->values();

        if ($raw->isEmpty()) {
            return [];
        }

        $packages = ClothingPackage::query()
            ->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get(['id', 'legacy_id']);

        $byId = $packages->keyBy('id');
        $byLegacy = $packages
            ->filter(fn (ClothingPackage $p) => filled($p->legacy_id))
            ->keyBy(fn (ClothingPackage $p) => (string) $p->legacy_id);

        $resolved = [];
        foreach ($raw as $id) {
            if (Str::isUuid($id) && $byId->has($id)) {
                $resolved[] = $id;

                continue;
            }

            if ($byLegacy->has($id)) {
                $resolved[] = $byLegacy->get($id)->id;
            }
        }

        return array_values(array_unique($resolved));
    }
}
