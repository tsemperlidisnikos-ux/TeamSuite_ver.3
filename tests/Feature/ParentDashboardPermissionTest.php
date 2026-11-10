<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ParentDashboardPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_without_dashboard_permission_does_not_see_preview_nav(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Parent Nav Club',
            'slug' => 'parent-nav-'.Str::random(6),
            'is_active' => true,
        ]);

        $parent = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $parent->clubs()->attach($club->id, [
            'role' => 'parent',
            'is_active' => true,
            'permissions' => json_encode(['fees', 'announcements', 'settings']),
        ]);

        $this->assertNotContains('dashboard', $parent->permissionsInClub($club));
        $this->assertNotContains('dashboard', $parent->allowedModulesInClub(
            ['dashboard', 'fees', 'announcements', 'settings'],
            $club,
        ));

        $props = [];
        $this->actingAs($parent)
            ->get(route('admin.fees.index'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$props) {
                $page->component('Admin/Fees/Index')
                    ->where('auth.user.role', 'parent');
                $props = $page->toArray()['props'] ?? [];
            });

        $modules = $props['navigation']['modules'] ?? [];
        $items = $props['navigation']['items'] ?? [];

        $this->assertIsArray($modules);
        $this->assertNotContains('dashboard', $modules);
        $this->assertContains('fees', $modules);
        $this->assertContains('announcements', $modules);
        $this->assertTrue(collect($items)->every(
            fn ($item) => ($item['id'] ?? null) !== 'dashboard'
        ));
    }
}
