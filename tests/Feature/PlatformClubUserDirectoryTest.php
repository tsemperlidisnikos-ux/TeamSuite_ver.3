<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use App\Support\UserPasswordVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformClubUserDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_sees_club_users_and_exports_xlsx(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Directory Club',
            'slug' => 'dir-'.Str::random(4),
            'is_active' => true,
        ]);

        $platformAdmin = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => true,
        ]);

        $clubAdmin = User::factory()->create([
            'name' => 'Παπαδόπουλος Νίκος',
            'email' => 'club-admin@example.com',
            'password' => 'secret-pass',
            'password_reveal' => UserPasswordVault::seal('secret-pass'),
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

        $otherClub = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Other Club',
            'slug' => 'other-'.Str::random(4),
            'is_active' => true,
        ]);
        $outsider = User::factory()->create([
            'email' => 'outsider@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => false,
            'active_club_id' => $otherClub->id,
        ]);
        $outsider->clubs()->attach($otherClub->id, [
            'role' => 'coach',
            'permissions' => json_encode([]),
            'finance_own_entries_only' => false,
            'is_active' => true,
        ]);

        $this->actingAs($platformAdmin)
            ->get(route('platform.club-users.index', ['club_id' => $club->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/ClubUserDirectory')
                ->where('clubId', $club->id)
                ->has('rows', 1)
                ->where('rows.0.email', 'club-admin@example.com')
                ->where('rows.0.last_name', 'Παπαδόπουλος')
                ->where('rows.0.first_name', 'Νίκος')
                ->where('rows.0.username', 'club-admin@example.com')
                ->where('rows.0.password', 'secret-pass')
            );

        $export = $this->actingAs($platformAdmin)
            ->get(route('platform.club-users.export', ['club_id' => $club->id]));

        $export->assertOk();
        $export->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $this->assertStringContainsString(
            'attachment;',
            (string) $export->headers->get('content-disposition')
        );
        $binary = $export->getContent();
        $this->assertGreaterThan(100, strlen($binary));

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $this->assertNotFalse($tmp);
        file_put_contents($tmp, $binary);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $shared = (string) $zip->getFromName('xl/sharedStrings.xml');
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($tmp);

        $this->assertStringContainsString('PASSWORD', $shared);
        $this->assertStringContainsString('secret-pass', $shared);
        $this->assertStringContainsString('dimension ref="A1:E', $sheet);
    }

    public function test_non_platform_admin_cannot_view_club_directory(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Blocked Club',
            'slug' => 'blocked-'.Str::random(4),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => false,
            'active_club_id' => $club->id,
        ]);
        $user->clubs()->attach($club->id, [
            'role' => 'admin',
            'permissions' => json_encode([]),
            'finance_own_entries_only' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('platform.club-users.index', ['club_id' => $club->id]))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('platform.club-users.export', ['club_id' => $club->id]))
            ->assertForbidden();
    }
}
