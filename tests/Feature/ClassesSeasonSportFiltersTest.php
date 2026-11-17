<?php

namespace Tests\Feature;

use App\Models\AcademyClass;
use App\Models\Club;
use App\Models\ClubSeason;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClassesSeasonSportFiltersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{club: Club, user: User, seasonA: ClubSeason, seasonB: ClubSeason, classBasket: AcademyClass, classVolleyball: AcademyClass}
     */
    private function seedClasses(): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Classes Filters Club',
            'slug' => 'classes-filters-'.Str::random(5),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $user->clubs()->attach($club->id, [
            'role' => 'admin',
            'is_active' => true,
            'permissions' => json_encode(['classes', 'dashboard']),
        ]);

        $seasonA = ClubSeason::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'name' => 'Σεζόν 2026-2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-30',
            'is_active' => true,
        ]);

        $seasonB = ClubSeason::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'name' => 'Σεζόν 2025-2026',
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-06-30',
            'is_active' => false,
        ]);

        Sport::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'name' => 'Μπάσκετ',
        ]);
        Sport::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'name' => 'Βόλεϊ',
        ]);

        $classBasket = AcademyClass::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'club_season_id' => $seasonA->id,
            'name' => 'U12 Μπάσκετ',
            'sport' => 'Μπάσκετ',
        ]);

        $classVolleyball = AcademyClass::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'club_season_id' => $seasonB->id,
            'name' => 'U14 Βόλεϊ',
            'sport' => 'Βόλεϊ',
        ]);

        return compact('club', 'user', 'seasonA', 'seasonB', 'classBasket', 'classVolleyball');
    }

    public function test_classes_index_exposes_registered_seasons_and_sports(): void
    {
        ['user' => $user, 'seasonA' => $seasonA] = $this->seedClasses();

        $this->actingAs($user)
            ->get(route('admin.classes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Classes/Index')
                ->has('seasons', 2)
                ->where('seasons.0.label', '2026-2027')
                ->where('seasons.0.id', $seasonA->id)
                ->has('sports', 2)
                ->where('sports', fn ($sports) => collect($sports)->contains('Μπάσκετ')
                    && collect($sports)->contains('Βόλεϊ'))
            );
    }

    public function test_classes_index_filters_by_season_and_sport(): void
    {
        [
            'user' => $user,
            'seasonA' => $seasonA,
            'classBasket' => $classBasket,
            'classVolleyball' => $classVolleyball,
        ] = $this->seedClasses();

        $this->actingAs($user)
            ->get(route('admin.classes.index', [
                'season_id' => $seasonA->id,
                'sport' => 'Μπάσκετ',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Classes/Index')
                ->where('filters.season_id', $seasonA->id)
                ->where('filters.sport', 'Μπάσκετ')
                ->has('items.data', 1)
                ->where('items.data.0.id', $classBasket->id)
                ->where('items.data.0.id', fn ($id) => $id !== $classVolleyball->id)
            );
    }
}
