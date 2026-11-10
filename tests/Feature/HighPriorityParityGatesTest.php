<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\Club;
use App\Models\Expense;
use App\Models\Revenue;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class HighPriorityParityGatesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{club: Club, user: User}
     */
    private function seedClubUser(string $role, bool $isPlatformAdmin = false, bool $ownFinance = false): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Parity Gates Club',
            'slug' => 'parity-gates-'.Str::random(6),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
            'is_platform_admin' => $isPlatformAdmin,
        ]);
        $user->clubs()->attach($club->id, [
            'role' => $role,
            'is_active' => true,
            'finance_own_entries_only' => $ownFinance,
            'permissions' => json_encode([]),
        ]);

        return compact('club', 'user');
    }

    public function test_coach_cannot_view_amka_on_athlete_profile(): void
    {
        ['club' => $club, 'user' => $coach] = $this->seedClubUser(Roles::COACH);
        $athlete = Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Νίκος',
            'last_name' => 'Δοκιμής',
            'status' => 'active',
            'amka_encrypted' => '12345678901',
            'amka_fingerprint' => hash('sha256', '12345678901'),
            'gdpr_consent' => 'pending',
        ]);

        $this->actingAs($coach)
            ->get(route('admin.athletes.show', $athlete->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canAmka', false)
                ->where('athlete.amka', '********')
            );
    }

    public function test_secretariat_cannot_manage_club_users(): void
    {
        ['club' => $club, 'user' => $user] = $this->seedClubUser(Roles::SECRETARIAT);

        $this->actingAs($user)
            ->post(route('admin.settings.users.store'), [
                'name' => 'Νέος',
                'email' => 'new-user-'.Str::random(4).'@example.com',
                'password' => 'password',
                'role' => Roles::COACH,
                'permissions' => [],
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.settings.index', ['tab' => 'users']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canManageUsers', false)
                ->where('tab', 'club')
            );
    }

    public function test_parent_is_redirected_away_from_finance_module(): void
    {
        ['user' => $parent] = $this->seedClubUser(Roles::PARENT);

        $this->actingAs($parent)
            ->get(route('admin.finance.index'))
            ->assertRedirect();
    }

    public function test_finance_own_entries_only_filters_list(): void
    {
        ['club' => $club, 'user' => $secretariat] = $this->seedClubUser(Roles::SECRETARIAT, false, true);

        $other = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $other->clubs()->attach($club->id, [
            'role' => Roles::COACH,
            'is_active' => true,
            'finance_own_entries_only' => false,
            'permissions' => json_encode([]),
        ]);

        Revenue::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'created_by' => $secretariat->id,
            'amount' => 10,
            'occurred_on' => now()->toDateString(),
            'category' => 'TEST',
        ]);
        Revenue::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'created_by' => $other->id,
            'amount' => 20,
            'occurred_on' => now()->toDateString(),
            'category' => 'TEST',
        ]);
        Expense::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'created_by' => $other->id,
            'amount' => 5,
            'occurred_on' => now()->toDateString(),
            'category' => 'TEST',
        ]);

        $this->actingAs($secretariat)
            ->get(route('admin.finance.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('revenues', 1)
                ->has('expenses', 0)
            );
    }

    public function test_non_platform_admin_cannot_bulk_edit_athletes(): void
    {
        ['club' => $club, 'user' => $admin] = $this->seedClubUser(Roles::ADMIN);
        $athlete = Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Α',
            'last_name' => 'Β',
            'status' => 'active',
            'gdpr_consent' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.athletes.bulk'), [
                'ids' => [$athlete->id],
                'status' => 'inactive',
            ])
            ->assertForbidden();
    }
}
