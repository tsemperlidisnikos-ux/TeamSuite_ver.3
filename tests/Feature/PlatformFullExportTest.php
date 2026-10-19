<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\Club;
use App\Models\User;
use App\Services\Backup\TeamSuitePlatformExporter;
use App\Services\Platform\PlatformConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformFullExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_download_full_platform_backup(): void
    {
        $clubA = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Alpha Club',
            'slug' => 'alpha-'.Str::random(4),
            'is_active' => true,
        ]);
        $clubB = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Beta Club',
            'slug' => 'beta-'.Str::random(4),
            'is_active' => true,
        ]);

        Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $clubA->id,
            'legacy_id' => 'a-1',
            'first_name' => 'Άννα',
            'last_name' => 'Αλεξίου',
            'status' => 'active',
        ]);
        Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $clubB->id,
            'legacy_id' => 'b-1',
            'first_name' => 'Βασίλης',
            'last_name' => 'Βασιλείου',
            'status' => 'active',
        ]);

        app(PlatformConfigService::class)->update(['appName' => 'TeamSuite Full']);

        $admin = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => true,
            'active_club_id' => null,
        ]);

        $clubUser = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => false,
            'active_club_id' => $clubA->id,
        ]);
        $clubUser->clubs()->attach($clubA->id, ['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)->get(route('platform.backup.download'));
        $response->assertOk();

        $payload = json_decode($response->streamedContent(), true);
        $this->assertIsArray($payload);
        $this->assertSame('platform', $payload['scope']);
        $this->assertNull($payload['sourceClubId']);
        $this->assertCount(2, $payload['clubs']);
        $this->assertArrayHasKey($clubA->id, $payload['appDataByClub']);
        $this->assertArrayHasKey($clubB->id, $payload['appDataByClub']);
        $this->assertSame('a-1', $payload['appDataByClub'][$clubA->id]['students'][0]['id'] ?? null);
        $this->assertSame('b-1', $payload['appDataByClub'][$clubB->id]['students'][0]['id'] ?? null);
        $this->assertSame('TeamSuite Full', $payload['platformConfig']['appName'] ?? null);
        $this->assertTrue(
            collect($payload['users'])->contains(fn ($u) => ($u['isPlatformAdmin'] ?? false) === true)
        );
    }

    public function test_non_platform_admin_cannot_download_full_backup(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Club',
            'slug' => 'club-'.Str::random(4),
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => false,
            'active_club_id' => $club->id,
        ]);
        $user->clubs()->attach($club->id, ['role' => 'admin', 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('platform.backup.download'))
            ->assertForbidden();
    }

    public function test_exporter_summary_counts_across_clubs(): void
    {
        $clubA = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'A',
            'slug' => 'a-'.Str::random(4),
            'is_active' => true,
        ]);
        $clubB = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'B',
            'slug' => 'b-'.Str::random(4),
            'is_active' => true,
        ]);

        Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $clubA->id,
            'first_name' => 'Α',
            'last_name' => 'Α',
            'status' => 'active',
        ]);
        Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $clubB->id,
            'first_name' => 'Β',
            'last_name' => 'Β',
            'status' => 'active',
        ]);

        $exporter = app(TeamSuitePlatformExporter::class);
        $payload = $exporter->export();
        $summary = $exporter->summarize($payload);

        $this->assertSame(2, $summary['clubs']);
        $this->assertSame(2, $summary['students']);
    }
}
