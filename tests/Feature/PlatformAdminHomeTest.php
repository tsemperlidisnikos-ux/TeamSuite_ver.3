<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\Club;
use App\Models\LicensePackage;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformAdminHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_lands_on_platform_dashboard_after_login(): void
    {
        $user = User::factory()->create([
            'email' => 'platform@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => true,
            'active_club_id' => null,
        ]);

        $this->post(route('login'), [
            'email' => 'platform@example.com',
            'password' => 'password',
        ])->assertRedirect(route('platform.dashboard'));

        $this->actingAs($user)
            ->get(route('platform.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/Dashboard')
                ->has('platformConfig')
                ->has('catalogs.academyModules')
                ->has('catalogs.financeTabs')
                ->where('catalogs.appearanceThemes', [
                    ['id' => 'ocean-slate', 'label' => 'Ocean Slate'],
                    ['id' => 'aegean-light', 'label' => 'Aegean Light'],
                    ['id' => 'harbor-navy', 'label' => 'Harbor Navy'],
                    ['id' => 'night-pitch', 'label' => 'Night Pitch'],
                ])
                ->where('platformBranding.appearanceTheme', 'ocean-slate')
            );
    }

    public function test_platform_admin_can_save_finance_tabs_and_modules(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Preview Club',
            'slug' => 'preview-'.Str::random(4),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => true,
            'active_club_id' => $club->id,
        ]);

        $this->actingAs($user)
            ->post(route('platform.settings.update'), [
                'appName' => 'TeamSuite Pro',
                'appearanceTheme' => 'harbor-navy',
                'financeTabs' => ['revenues', 'expenses', 'accounts'],
                'academyModulesByClub' => [
                    $club->id => ['dashboard', 'athletes', 'fees', 'settings'],
                ],
                'seasons' => ['2026-2027', '2027-2028'],
            ])
            ->assertRedirect();

        $stored = PlatformSetting::query()->where('key', 'platform_config')->first();
        $this->assertNotNull($stored);
        $this->assertSame('TeamSuite Pro', $stored->value['appName']);
        $this->assertSame(['revenues', 'expenses', 'accounts'], $stored->value['financeTabs']);
        $this->assertSame(['2026-2027', '2027-2028'], $stored->value['seasons']);
        $this->assertSame(
            ['dashboard', 'athletes', 'fees', 'settings'],
            $stored->value['academyModulesByClub'][$club->id],
        );
    }

    public function test_club_header_title_is_saved_and_shared_to_admin_chrome(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Α.Σ. ΑΠΟΛΛΩΝ ΠΑΤΡΩΝ',
            'slug' => 'apollon-'.Str::random(4),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => true,
            'active_club_id' => $club->id,
        ]);
        $user->clubs()->attach($club->id, [
            'role' => 'admin',
            'permissions' => json_encode([]),
            'finance_own_entries_only' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('platform.settings.update'), [
                'clubHeaderTitles' => [
                    $club->id => 'ApollonPatras',
                ],
            ])
            ->assertRedirect();

        $stored = PlatformSetting::query()->where('key', 'platform_config')->first();
        $this->assertSame('ApollonPatras', $stored?->value['clubHeaderTitles'][$club->id] ?? null);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentClub.header_title', 'ApollonPatras')
                ->where('currentClub.name', 'Α.Σ. ΑΠΟΛΛΩΝ ΠΑΤΡΩΝ')
            );
    }

    public function test_platform_admin_starts_club_preview_without_leaving_management(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Α.Σ. ΑΠΟΛΛΩΝ ΠΑΤΡΩΝ',
            'slug' => 'apollon-'.Str::random(4),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => true,
            'active_club_id' => null,
        ]);

        $this->actingAs($user)
            ->post(route('platform.clubs.activate', $club->id), ['preview' => true])
            ->assertRedirect(route('platform.dashboard', ['tab' => 'club', 'item' => 'preview']));

        $this->assertSame($club->id, $user->fresh()->active_club_id);
        $this->assertSame($club->id, session('platform_preview_club_id'));

        $this->actingAs($user)
            ->get(route('platform.dashboard', ['tab' => 'club', 'item' => 'preview']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activePreview.id', $club->id)
                ->where('activePreview.name', $club->name)
            );
    }

    public function test_platform_clubs_list_shows_active_and_total_athletes(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Athletes Club',
            'slug' => 'athletes-'.Str::random(4),
            'is_active' => true,
        ]);

        Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Ενεργός',
            'last_name' => 'Α',
            'status' => 'active',
            'gdpr_consent' => 'pending',
        ]);
        Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Ανενεργός',
            'last_name' => 'Β',
            'status' => 'inactive',
            'gdpr_consent' => 'pending',
        ]);
        Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Δοκιμή',
            'last_name' => 'Γ',
            'status' => 'trial',
            'gdpr_consent' => 'pending',
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => true,
            'active_club_id' => $club->id,
        ]);

        $this->actingAs($user)
            ->get(route('platform.clubs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/Clubs')
                ->where('clubs.0.athletes_count', 3)
                ->where('clubs.0.athletes_active_count', 1)
            );
    }

    public function test_platform_admin_can_assign_license_package_to_club(): void
    {
        $package = LicensePackage::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Όριο 1000',
            'athlete_cap' => 1000,
            'modules' => ['*'],
            'is_active' => true,
        ]);

        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'License Club',
            'slug' => 'license-'.Str::random(4),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => true,
            'active_club_id' => $club->id,
        ]);

        $this->actingAs($user)
            ->patch(route('platform.clubs.license', $club->id), [
                'license_package_id' => $package->id,
            ])
            ->assertRedirect();

        $this->assertSame($package->id, $club->fresh()->license_package_id);
        $this->assertSame(1000, (int) $club->fresh('licensePackage')->licensePackage?->athlete_cap);
    }

    public function test_club_admin_still_lands_on_club_dashboard(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Club',
            'slug' => 'club-'.Str::random(4),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'email' => 'clubadmin@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => false,
            'active_club_id' => $club->id,
        ]);
        $user->clubs()->attach($club->id, ['role' => 'admin', 'is_active' => true]);

        $this->post(route('login'), [
            'email' => 'clubadmin@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));
    }
}
