<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LoginHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_view_login_history_with_role_and_club(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Αθλητικός Σύλλογος',
            'slug' => 'club-'.Str::random(4),
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'name' => 'Platform Admin',
            'email' => 'platform@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => true,
            'active_club_id' => null,
        ]);

        $coach = User::factory()->create([
            'name' => 'Coach User',
            'email' => 'coach@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => false,
            'active_club_id' => $club->id,
        ]);

        $coach->clubs()->attach($club->id, [
            'role' => 'coach',
            'permissions' => json_encode([]),
            'finance_own_entries_only' => false,
            'is_active' => true,
        ]);

        LoginActivity::query()->create([
            'user_id' => $admin->id,
            'club_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'logged_in_at' => now()->subMinute(),
        ]);

        LoginActivity::query()->create([
            'user_id' => $coach->id,
            'club_id' => $club->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'logged_in_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('platform.logins.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/LoginHistory')
                ->has('rows', 2)
                ->where('rows.0.user', 'Coach User')
                ->where('rows.0.club_name', 'Αθλητικός Σύλλογος')
                ->where('rows.0.role_label', 'Προπονητής')
                ->where('rows.0.type_label', 'Σύνδεση')
                ->where('rows.1.club_name', 'Πλατφόρμα')
                ->where('rows.1.role_label', 'Διαχειριστής πλατφόρμας')
            );
    }

    public function test_successful_login_creates_login_activity(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => true,
            'active_club_id' => null,
        ]);

        $this->post(route('login'), [
            'email' => 'login@example.com',
            'password' => 'password',
        ])->assertRedirect(route('platform.dashboard'));

        $this->assertDatabaseHas('login_activities', [
            'user_id' => $user->id,
            'club_id' => null,
        ]);
    }

    public function test_platform_admin_can_delete_login_history(): void
    {
        $admin = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => true,
            'active_club_id' => null,
        ]);

        $row = LoginActivity::query()->create([
            'user_id' => $admin->id,
            'club_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'logged_in_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('platform.logins.destroy', $row))
            ->assertRedirect();

        $this->assertDatabaseMissing('login_activities', ['id' => $row->id]);
    }

    public function test_non_admin_cannot_view_login_history(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => false,
        ]);

        $this->actingAs($user)
            ->get(route('platform.logins.index'))
            ->assertForbidden();
    }
}
