<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScreenParityU0Test extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{club: Club, user: User}
     */
    private function seedClubUser(): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Parity Club',
            'slug' => 'parity-'.Str::random(6),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $user->clubs()->attach($club->id, ['role' => 'admin', 'is_active' => true]);

        return compact('club', 'user');
    }

    public function test_login_screen_exposes_parent_portal_mode(): void
    {
        $this->get('/login?app=parent')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('portalApp', 'parent')
            );
    }

    public function test_login_screen_exposes_coach_portal_mode(): void
    {
        $this->get('/login?app=coach')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('portalApp', 'coach')
            );
    }

    public function test_legacy_athletes_path_redirects_to_admin(): void
    {
        ['user' => $user] = $this->seedClubUser();

        $this->actingAs($user)
            ->get('/athletes')
            ->assertRedirect('/admin/athletes');
    }

    public function test_legacy_students_path_redirects_to_athletes(): void
    {
        ['user' => $user] = $this->seedClubUser();

        $this->actingAs($user)
            ->get('/students')
            ->assertRedirect('/admin/athletes');
    }

    public function test_dashboard_shares_club_alerts(): void
    {
        ['user' => $user] = $this->seedClubUser();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('kpis')
                ->where('clubAlerts.pendingRegistrations', 0)
                ->where('clubAlerts.lowStock', 0)
            );
    }

    public function test_club_backup_download_returns_json(): void
    {
        ['user' => $user] = $this->seedClubUser();

        $response = $this->actingAs($user)
            ->get(route('admin.settings.backup.download'));

        $response->assertOk();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('content-type'));
    }
}
