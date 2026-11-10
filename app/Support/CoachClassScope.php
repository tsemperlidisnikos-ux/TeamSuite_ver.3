<?php

namespace App\Support;

use App\Models\AcademyClass;
use App\Models\Club;
use App\Models\Coach;
use App\Models\User;
use Illuminate\Http\Request;

final class CoachClassScope
{
    /**
     * Active coach card for a club user: linked user_id, else matching email.
     */
    public static function resolveCoach(User $user, string $clubId): ?Coach
    {
        return Coach::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('email', $user->email);
            })
            ->where('is_active', true)
            ->first();
    }

    /**
     * Class ids owned by the coach (`academy_classes.coach_id`).
     *
     * @return list<string>|null null = not a coach (no class scope)
     */
    public static function classIdsFor(?User $user, ?Club $club): ?array
    {
        if (! $user || ! $club || $user->is_platform_admin) {
            return null;
        }

        if ($user->roleInClub($club) !== Roles::COACH) {
            return null;
        }

        $coach = self::resolveCoach($user, $club->id);
        if (! $coach) {
            return [];
        }

        return AcademyClass::query()->withoutGlobalScopes()
            ->where('club_id', $club->id)
            ->where('coach_id', $coach->id)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    /**
     * @return list<string>|null null = not a coach (no class scope)
     */
    public static function classIdsForRequest(Request $request): ?array
    {
        $user = $request->user();
        $club = $request->attributes->get('currentClub') ?? $user?->activeClub;

        return self::classIdsFor($user, $club);
    }

    public static function allowsClass(?array $allowedClassIds, string $classId): bool
    {
        if ($allowedClassIds === null) {
            return true;
        }

        return in_array($classId, $allowedClassIds, true);
    }
}
