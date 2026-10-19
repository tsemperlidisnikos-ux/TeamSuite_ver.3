<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Athlete;
use App\Models\Club;
use App\Models\ClubSeason;
use App\Models\Facility;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UpgradeModulesTest extends TestCase
{
    use RefreshDatabase;

    private function seedAdmin(): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Upgrade Club',
            'slug' => 'upgrade-'.Str::random(5),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $user->clubs()->attach($club->id, ['role' => 'admin', 'is_active' => true]);

        $athlete = Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Ελένη',
            'last_name' => 'Κωνσταντίνου',
            'status' => 'active',
            'monthly_fee' => 45,
            'gdpr_consent' => 'pending',
        ]);

        return compact('club', 'user', 'athlete');
    }

    public function test_fees_bulk_charge_and_debt_list(): void
    {
        ['user' => $user, 'athlete' => $athlete] = $this->seedAdmin();

        $this->actingAs($user)
            ->post(route('admin.fees.bulk-charge'), [
                'athlete_ids' => [$athlete->id],
                'use_monthly_fee' => true,
                'month' => now()->month,
                'year' => now()->year,
                'comments' => 'Μηνιαία',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('athlete_transactions', [
            'athlete_id' => $athlete->id,
            'type' => 'charge',
            'amount' => 45,
        ]);

        $this->actingAs($user)
            ->get(route('admin.fees.index', ['owed' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Fees/Index')
                ->where('totals.with_debt', 1)
            );
    }

    public function test_announcements_with_audience(): void
    {
        ['user' => $user] = $this->seedAdmin();
        Storage::fake('public');

        $image = UploadedFile::fake()->image('announcement.png', 320, 240);

        $this->actingAs($user)
            ->post(route('admin.announcements.store'), [
                'title' => 'Αναβολή προπόνησης',
                'body' => 'Αύριο δεν γίνεται προπόνηση.',
                'roles' => ['parent', 'athlete'],
                'class_ids' => [],
                'publish' => true,
                'send_email' => false,
                'image' => $image,
            ])
            ->assertRedirect();

        $row = Announcement::query()->withoutGlobalScopes()->first();
        $this->assertNotNull($row);
        $this->assertNotEmpty($row->meta['image_path'] ?? null);
        $this->assertTrue(Storage::disk('public')->exists($row->meta['image_path']));

        $this->actingAs($user)
            ->get(route('admin.announcements.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Announcements/Index')
                ->has('items.data', 1)
                ->has('audienceOptions.parents')
                ->has('audienceOptions.coaches')
                ->has('audienceOptions.athletes')
                ->has('audienceOptions.staff')
                ->where('items.data.0.image_url', fn ($url) => is_string($url) && $url !== '')
            );
    }

    public function test_club_settings_sports_facilities_seasons(): void
    {
        ['user' => $user, 'club' => $club] = $this->seedAdmin();

        $this->actingAs($user)
            ->post(route('admin.settings.sports.store'), ['name' => 'Μπάσκετ'])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('admin.settings.facilities.store'), [
                'name' => 'Κλειστό Α',
                'address' => 'Οδός 1',
                'is_rentable' => true,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('admin.settings.seasons.store'), [
                'name' => '2025-26',
                'starts_on' => '2025-09-01',
                'ends_on' => '2026-06-30',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertSame(1, Sport::query()->withoutGlobalScopes()->where('club_id', $club->id)->count());
        $this->assertSame(1, Facility::query()->withoutGlobalScopes()->where('club_id', $club->id)->count());
        $this->assertSame(1, ClubSeason::query()->withoutGlobalScopes()->where('club_id', $club->id)->where('is_active', true)->count());

        $this->actingAs($user)
            ->get(route('admin.settings.index', ['tab' => 'sports']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Settings/Index')
                ->has('sports', 1)
            );
    }

    public function test_club_settings_profile_email_and_catalog_tabs(): void
    {
        ['user' => $user, 'club' => $club] = $this->seedAdmin();

        $this->actingAs($user)
            ->post(route('admin.settings.club.update'), [
                'name' => 'Upgrade Club Updated',
                'vat_number' => '123456789',
                'phone' => '2100000000',
                'custom_charge_label' => 'Στολή',
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'club']));

        $this->assertSame('Upgrade Club Updated', $club->fresh()->name);
        $this->assertSame('123456789', $club->fresh()->settings->profile['vat_number'] ?? null);

        // Mimic Inertia forceFormData: booleans arrive as "false"/"true" or 0/1.
        $logo = UploadedFile::fake()->image('club-logo.png', 256, 256);
        $this->actingAs($user)
            ->post(route('admin.settings.club.update'), [
                'name' => 'Upgrade Club Updated',
                'remove_logo' => 0,
                'logo' => $logo,
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'club']));

        $club->refresh();
        $this->assertNotNull($club->logo_path);
        $this->assertStringContainsString('club-logos/', $club->logo_path);
        $this->assertTrue(Storage::disk('public')->exists($club->logo_path));

        $this->actingAs($user)
            ->post(route('admin.settings.email.update'), [
                'enabled' => true,
                'host' => 'smtp.example.com',
                'port' => '587',
                'username' => 'club@example.com',
                'password' => 'secret',
                'fromEmail' => 'club@example.com',
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'email']));

        $this->assertSame('smtp.example.com', $club->fresh()->settings->smtp['host'] ?? null);

        $this->actingAs($user)
            ->post(route('admin.settings.associations.store'), [
                'name' => 'ΕΠΣ',
                'city' => 'Αθήνα',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('admin.settings.clothing.store'), ['name' => 'Βασικό'])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('admin.settings.discounts.store'), ['name' => 'Αδέρφια', 'sport' => ''])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('admin.settings.sizes.update'), ['sizes' => ['S', 'M', 'L']])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('admin.settings.index', ['tab' => 'email']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Settings/Index')
                ->where('tab', 'email')
                ->has('associations', 1)
                ->has('clothingPackages', 1)
                ->has('discountReasons', 1)
            );
    }

    public function test_backup_restore_end_to_end_via_http(): void
    {
        ['user' => $user] = $this->seedAdmin();

        $json = file_get_contents(base_path('tests/fixtures/teamsuite-club-backup-minimal.json'));
        $file = UploadedFile::fake()->createWithContent('club.json', $json);

        $this->actingAs($user)
            ->post(route('admin.settings.backup.preview'), ['backup_file' => $file])
            ->assertRedirect(route('admin.settings.backup.index'));

        $backupId = session('backup_dry_run')['backupId'] ?? null;
        $this->assertNotEmpty($backupId);

        $this->actingAs($user)
            ->withSession([
                'backup_pending_json' => $json,
                'backup_dry_run' => session('backup_dry_run'),
            ])
            ->post(route('admin.settings.backup.restore'), [
                'backup_id' => $backupId,
                'mode' => 'merge',
            ])
            ->assertRedirect(route('admin.settings.backup.index'));

        $this->assertSame(
            2,
            Athlete::query()->withoutGlobalScopes()->where('club_id', 'club-legacy-001')->count()
        );
    }
}
