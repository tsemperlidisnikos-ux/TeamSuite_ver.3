<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_users_index_and_store(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Users Club',
            'slug' => 'users-'.Str::random(4),
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('platform.users.index', ['role' => 'platform_admin']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/Users')
                ->where('role', 'platform_admin')
                ->has('drill', 3)
            );

        $this->actingAs($admin)
            ->post(route('platform.users.store'), [
                'name' => 'Νέος Admin',
                'email' => 'club-admin@example.com',
                'password' => 'password123',
                'role' => 'admin',
                'club_id' => $club->id,
            ])
            ->assertRedirect();

        $created = User::query()->where('email', 'club-admin@example.com')->first();
        $this->assertNotNull($created);
        $this->assertFalse((bool) $created->is_platform_admin);
        $this->assertTrue($created->clubs()->where('clubs.id', $club->id)->wherePivot('role', 'admin')->exists());
    }

    public function test_platform_admin_can_enter_as_club_user_and_leave(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Enter Club',
            'slug' => 'enter-'.Str::random(4),
            'is_active' => true,
        ]);

        $platformAdmin = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => true,
        ]);

        $clubAdmin = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => false,
            'active_club_id' => $club->id,
        ]);
        $clubAdmin->clubs()->attach($club->id, [
            'role' => 'admin',
            'permissions' => json_encode([]),
            'finance_own_entries_only' => false,
            'is_active' => true,
        ]);

        $this->actingAs($platformAdmin)
            ->post(route('platform.users.enter'), [
                'user_id' => $clubAdmin->id,
                'club_id' => $club->id,
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($clubAdmin);
        $this->assertEquals($platformAdmin->id, session('impersonator_id'));

        $this->actingAs($clubAdmin)
            ->withSession(['impersonator_id' => $platformAdmin->id])
            ->post(route('platform.users.leave'))
            ->assertRedirect(route('platform.users.index'));

        $this->assertAuthenticatedAs($platformAdmin);
        $this->assertNull(session('impersonator_id'));
    }

    public function test_enter_accepts_legacy_club_ids(): void
    {
        $club = Club::query()->create([
            'id' => 'club_'.Str::random(10),
            'name' => 'Legacy Club',
            'slug' => 'legacy-'.Str::random(4),
            'is_active' => true,
        ]);

        $platformAdmin = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => true,
        ]);

        $clubAdmin = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => false,
            'active_club_id' => $club->id,
        ]);
        $clubAdmin->clubs()->attach($club->id, [
            'role' => 'admin',
            'permissions' => json_encode([]),
            'finance_own_entries_only' => false,
            'is_active' => true,
        ]);

        $this->actingAs($platformAdmin)
            ->post(route('platform.users.enter'), [
                'user_id' => $clubAdmin->id,
                'club_id' => $club->id,
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($clubAdmin);
        $this->assertSame($club->id, $clubAdmin->fresh()->active_club_id);
    }
}
