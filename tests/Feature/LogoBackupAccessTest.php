<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LogoBackupAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{club: Club, user: User}
     */
    private function seedClubUser(string $role, bool $isPlatformAdmin = false): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Logo Backup Club',
            'slug' => 'logo-backup-'.Str::random(6),
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
        ]);

        return compact('club', 'user');
    }

    public static function allowedRolesProvider(): array
    {
        return [
            'club admin' => [Roles::ADMIN, false],
            'secretariat' => [Roles::SECRETARIAT, false],
            'platform admin' => [Roles::ADMIN, true],
        ];
    }

    public static function deniedRolesProvider(): array
    {
        return [
            'parent' => [Roles::PARENT],
            'coach' => [Roles::COACH],
            'staff' => [Roles::STAFF],
            'doctor' => [Roles::DOCTOR],
            'athlete' => [Roles::ATHLETE],
        ];
    }

    #[DataProvider('allowedRolesProvider')]
    public function test_allowed_roles_can_download_club_backup(string $role, bool $isPlatformAdmin): void
    {
        ['user' => $user] = $this->seedClubUser($role, $isPlatformAdmin);

        $this->actingAs($user)
            ->get(route('admin.settings.backup.download'))
            ->assertOk();
    }

    #[DataProvider('deniedRolesProvider')]
    public function test_denied_roles_cannot_download_club_backup(string $role): void
    {
        ['user' => $user] = $this->seedClubUser($role);

        $response = $this->actingAs($user)
            ->get(route('admin.settings.backup.download'));

        // Without the settings module the club.module middleware redirects home;
        // with settings but without backup privilege the controller returns 403.
        $this->assertTrue(
            in_array($response->status(), [302, 403], true),
            'Expected 302 or 403, got '.$response->status(),
        );
    }

    public function test_can_quick_club_backup_helper_matches_allowed_roles(): void
    {
        $this->assertTrue(Roles::canQuickClubBackup(true, Roles::PARENT));
        $this->assertTrue(Roles::canQuickClubBackup(false, Roles::ADMIN));
        $this->assertTrue(Roles::canQuickClubBackup(false, Roles::SECRETARIAT));
        $this->assertFalse(Roles::canQuickClubBackup(false, Roles::PARENT));
        $this->assertFalse(Roles::canQuickClubBackup(false, Roles::COACH));
        $this->assertFalse(Roles::canQuickClubBackup(false, null));
    }

    public function test_parent_cannot_preview_or_restore_club_backup(): void
    {
        ['user' => $user] = $this->seedClubUser(Roles::PARENT);

        $json = file_get_contents(base_path('tests/fixtures/teamsuite-club-backup-minimal.json'));
        $file = UploadedFile::fake()->createWithContent('club.json', $json);

        $preview = $this->actingAs($user)->post(route('admin.settings.backup.preview'), [
            'backup_file' => $file,
        ]);
        $this->assertSame(
            403,
            $preview->status(),
            'Parent preview expected 403, got '.$preview->status(),
        );

        $restore = $this->actingAs($user)->post(route('admin.settings.backup.restore'), [
            'backup_id' => (string) Str::uuid(),
            'mode' => 'merge',
        ]);
        $this->assertSame(
            403,
            $restore->status(),
            'Parent restore expected 403, got '.$restore->status(),
        );

        $confirm = $this->actingAs($user)->post(route('admin.settings.backup.confirm'), [
            'backup_id' => (string) Str::uuid(),
            'mode' => 'merge',
        ]);
        $this->assertSame(
            403,
            $confirm->status(),
            'Parent confirm expected 403, got '.$confirm->status(),
        );
    }
}
