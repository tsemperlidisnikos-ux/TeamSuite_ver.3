<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Coach;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClubSettingsUsersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, club: Club}
     */
    private function seedAdmin(): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Users Club',
            'slug' => 'users-'.Str::random(5),
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
            'permissions' => json_encode(['dashboard', 'settings']),
        ]);

        return compact('user', 'club');
    }

    public function test_settings_users_tab_includes_permission_catalog(): void
    {
        ['user' => $user] = $this->seedAdmin();

        $this->actingAs($user)
            ->get(route('admin.settings.index', ['tab' => 'users']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Settings/Index')
                ->where('tab', 'users')
                ->has('permissionModules')
                ->has('roleDefaultPermissions.coach')
                ->has('coaches')
                ->has('users')
            );
    }

    public function test_store_user_with_permissions_and_coach_link(): void
    {
        ['user' => $admin, 'club' => $club] = $this->seedAdmin();

        $coach = Coach::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Νίκος',
            'last_name' => 'Προπονητής',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.settings.users.store'), [
                'name' => 'Παπαδόπουλος Γιάννης',
                'email' => 'coach.user@example.com',
                'password' => 'secret1',
                'role' => 'coach',
                'permissions' => ['dashboard', 'calendar', 'athletes'],
                'finance_own_entries_only' => 0,
                'is_active' => 1,
                'coach_id' => $coach->id,
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'users']));

        $created = User::query()->where('email', 'coach.user@example.com')->first();
        $this->assertNotNull($created);
        $this->assertTrue($club->users()->where('users.id', $created->id)->exists());

        $pivot = $club->users()->where('users.id', $created->id)->first()?->pivot;
        $permissions = is_string($pivot?->permissions)
            ? json_decode($pivot->permissions, true)
            : ($pivot?->permissions ?? []);
        $this->assertSame(['dashboard', 'calendar', 'athletes'], array_values($permissions));

        $this->assertSame($created->id, $coach->fresh()->user_id);
    }

    public function test_update_user_can_toggle_inactive(): void
    {
        ['user' => $admin, 'club' => $club] = $this->seedAdmin();

        $member = User::factory()->create([
            'name' => 'Μέλος Δοκιμής',
            'email' => 'member@example.com',
            'active_club_id' => $club->id,
        ]);
        $club->users()->attach($member->id, [
            'role' => 'parent',
            'is_active' => true,
            'permissions' => json_encode(['dashboard', 'fees']),
            'finance_own_entries_only' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.settings.users.update', $member->id), [
                'name' => 'Μέλος Δοκιμής',
                'role' => 'parent',
                'permissions' => ['dashboard', 'fees'],
                'finance_own_entries_only' => 0,
                'is_active' => 0,
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'users']));

        $this->assertFalse((bool) $club->users()->where('users.id', $member->id)->first()?->pivot->is_active);
    }
}
