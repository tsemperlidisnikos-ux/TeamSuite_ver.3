<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserModulePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_sidebar_and_dashboard_respect_user_permissions(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Permissions Club',
            'slug' => 'perm-'.Str::random(4),
            'is_active' => true,
        ]);

        $coach = User::factory()->create([
            'name' => 'Coach Limited',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
            'is_platform_admin' => false,
        ]);
        $coach->clubs()->attach($club->id, [
            'role' => 'coach',
            'permissions' => json_encode([
                'dashboard',
                'calendar',
                'athletes',
                'classes',
                'trainings',
                'matches',
                'schedule',
                'attendance',
                'announcements',
                'photos',
            ]),
            'finance_own_entries_only' => false,
            'is_active' => true,
        ]);

        $props = [];
        $this->actingAs($coach)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$props) {
                $page->component('Admin/Dashboard')
                    ->where('showFinance', false)
                    ->where('showRentals', false)
                    ->where('money', null)
                    ->where('auth.user.role', 'coach');
                $props = $page->toArray()['props'] ?? [];
            });

        $modules = $props['navigation']['modules'] ?? [];
        $permissions = $props['auth']['user']['permissions'] ?? [];

        $this->assertContains('photos', $permissions);
        $this->assertContains('photos', $modules);
        $this->assertContains('athletes', $modules);
        $this->assertNotContains('finance', $modules);
        $this->assertNotContains('rental', $modules);
        $this->assertNotContains('settings', $modules);
        $this->assertNotContains('staff', $modules);
    }
}
