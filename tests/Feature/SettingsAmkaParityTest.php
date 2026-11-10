<?php

namespace Tests\Feature;

use App\Models\AmkaAccessLog;
use App\Models\Athlete;
use App\Models\Club;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsAmkaParityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{club: Club, user: User}
     */
    private function seedClubUser(string $role, bool $isPlatformAdmin = false): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'AMKA Parity Club',
            'slug' => 'amka-parity-'.Str::random(6),
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
            'permissions' => json_encode(['settings', 'athletes']),
        ]);

        return compact('club', 'user');
    }

    public function test_amka_settings_tab_includes_access_logs_prop(): void
    {
        ['club' => $club, 'user' => $admin] = $this->seedClubUser(Roles::ADMIN);

        $athlete = Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Μαρία',
            'last_name' => 'Παπα',
            'status' => 'active',
        ]);

        AmkaAccessLog::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'athlete_id' => $athlete->id,
            'user_id' => $admin->id,
            'action' => 'amka.read',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.settings.index', ['tab' => 'amka']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Settings/Index')
                ->where('tab', 'amka')
                ->where('canAccessAmka', true)
                ->where('canManageGdpr', true)
                ->has('amkaAccessLogs', 1)
                ->where('amkaAccessLogs.0.action', 'amka.read')
                ->where('amkaAccessLogs.0.athlete_name', 'Παπα Μαρία')
                ->has('settings.dpia_checklist')
                ->has('dpiaChecklistItems')
            );
    }

    public function test_doctor_sees_amka_logs_but_cannot_manage_gdpr(): void
    {
        ['club' => $club, 'user' => $doctor] = $this->seedClubUser(Roles::DOCTOR);

        AmkaAccessLog::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'athlete_id' => null,
            'user_id' => $doctor->id,
            'action' => 'amka.write',
        ]);

        $this->actingAs($doctor)
            ->get(route('admin.settings.index', ['tab' => 'amka']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'amka')
                ->where('canAccessAmka', true)
                ->where('canManageGdpr', false)
                ->has('amkaAccessLogs', 1)
                ->where('gdprAthletes', [])
            );
    }

    public function test_public_registration_settings_expose_slug_for_join_qr(): void
    {
        ['club' => $club, 'user' => $admin] = $this->seedClubUser(Roles::ADMIN);
        $club->settings()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'public_join_enabled' => true,
            'public_registration' => [
                'enabled' => true,
                'slug' => $club->slug,
                'notifyEmail' => 'office@example.com',
                'allowTrial' => true,
                'allowWaitlist' => false,
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.settings.index', ['tab' => 'publicRegistration']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'publicRegistration')
                ->where('settings.public_registration.slug', $club->slug)
                ->where('club.slug', $club->slug)
            );
    }
}
