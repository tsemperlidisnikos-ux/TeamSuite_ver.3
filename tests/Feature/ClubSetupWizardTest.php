<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClubSetupWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * @return array{user: User, club: Club}
     */
    private function seedClubUser(string $role, array $permissions = ['dashboard', 'settings']): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Setup Wizard Club',
            'slug' => 'setup-'.Str::random(6),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $user->clubs()->attach($club->id, [
            'role' => $role,
            'is_active' => true,
            'permissions' => json_encode($permissions),
        ]);

        return compact('user', 'club');
    }

    public function test_admin_can_open_club_setup_wizard(): void
    {
        ['user' => $user] = $this->seedClubUser(Roles::ADMIN);

        $this->actingAs($user)
            ->get(route('admin.setup.wizard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Setup/Wizard')
                ->has('progress')
                ->has('club')
                ->has('publicJoin')
                ->has('payments')
            );
    }

    public function test_coach_cannot_open_club_setup_wizard(): void
    {
        // Settings module permission so EnsureClubModule passes; role gate must still forbid.
        ['user' => $user] = $this->seedClubUser(Roles::COACH, ['dashboard', 'settings']);

        $this->actingAs($user)
            ->get(route('admin.setup.wizard'))
            ->assertForbidden();
    }
}
