<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\Club;
use App\Models\RegistrationApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AthletesListParityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{club: Club, user: User}
     */
    private function seedClubUser(): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Athletes Club',
            'slug' => 'ath-'.Str::random(6),
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

    public function test_athletes_index_includes_pending_applications_and_health_notices(): void
    {
        ['club' => $club, 'user' => $user] = $this->seedClubUser();

        Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Νίκος',
            'last_name' => 'Παπαδόπουλος',
            'status' => 'active',
            'health_card_expires' => now()->subDay()->toDateString(),
        ]);

        RegistrationApplication::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'type' => 'full',
            'status' => 'pending',
            'first_name' => 'Μαρία',
            'last_name' => 'Ιωάννου',
            'email' => 'maria@example.com',
            'gdpr_accepted' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.athletes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Athletes/Index')
                ->has('pendingApplications', 1)
                ->has('healthNoticeRows', 1)
                ->where('stats.active', 1)
            );
    }

    public function test_approve_application_creates_athlete(): void
    {
        ['club' => $club, 'user' => $user] = $this->seedClubUser();

        $app = RegistrationApplication::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'type' => 'trial',
            'status' => 'pending',
            'first_name' => 'Γιώργος',
            'last_name' => 'Κωνσταντίνου',
            'email' => 'g@example.com',
            'gdpr_accepted' => true,
        ]);

        $this->actingAs($user)
            ->post(route('admin.athletes.applications.approve', $app->id))
            ->assertRedirect();

        $this->assertDatabaseHas('athletes', [
            'club_id' => $club->id,
            'first_name' => 'Γιώργος',
            'last_name' => 'Κωνσταντίνου',
            'status' => 'trial',
        ]);

        $this->assertDatabaseHas('registration_applications', [
            'id' => $app->id,
            'status' => 'approved',
        ]);
    }

    public function test_bulk_status_update(): void
    {
        ['club' => $club, 'user' => $user] = $this->seedClubUser();

        $a = Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Α',
            'last_name' => 'Β',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('admin.athletes.bulk'), [
                'ids' => [$a->id],
                'status' => 'inactive',
            ])
            ->assertRedirect();

        $this->assertSame('inactive', $a->fresh()->status);
    }
}
