<?php

namespace Tests\Feature;

use App\Models\AcademyClass;
use App\Models\Announcement;
use App\Models\Athlete;
use App\Models\AthleteTransaction;
use App\Models\Attendance;
use App\Models\Club;
use App\Models\ClubSetting;
use App\Models\ParentAthleteLink;
use App\Models\ReceiptIssue;
use App\Models\ReceiptSeries;
use App\Models\ScheduleSlot;
use App\Models\SizeChart;
use App\Models\Training;
use App\Models\User;
use App\Services\Backup\TeamSuiteBackupParser;
use App\Services\Backup\TeamSuiteBackupRestoreService;
use App\Support\PreferredNotifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeamSuiteBackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_counts_club_backup_fixture(): void
    {
        $json = file_get_contents(base_path('tests/fixtures/teamsuite-club-backup-minimal.json'));
        $parser = new TeamSuiteBackupParser;
        $parsed = $parser->parse($json);

        $this->assertSame('club', $parsed['scope']);
        $this->assertSame('club-legacy-001', $parsed['sourceClubId']);

        $counts = $parser->countAppData($parsed['appDataByClub']['club-legacy-001']);
        $this->assertSame(3, $counts['students']);
        $this->assertSame(3, $counts['clubMembers']);
        $this->assertSame(1, $counts['transactions']);
    }

    public function test_ensure_club_creates_same_source_club_id(): void
    {
        $json = file_get_contents(base_path('tests/fixtures/teamsuite-club-backup-minimal.json'));
        $service = app(TeamSuiteBackupRestoreService::class);
        $parsed = app(TeamSuiteBackupParser::class)->parse($json);

        $club = $service->ensureClubFromPayload($parsed);

        $this->assertSame('club-legacy-001', $club->id);
        $this->assertSame('Α.Σ. Απόλλων Πατρών', $club->name);
        $this->assertNotNull($club->settings);
        $this->assertNotNull($club->license_package_id);
        $this->assertSame(1000, (int) $club->licensePackage?->athlete_cap);
    }

    public function test_restore_preserves_existing_secrets_when_incoming_masked(): void
    {
        $club = Club::query()->create([
            'id' => 'club-legacy-001',
            'name' => 'Existing',
            'slug' => 'existing',
            'is_active' => true,
        ]);

        ClubSetting::query()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'smtp' => ['host' => 'old.smtp', 'password' => 'real-secret'],
            'sms' => ['apiKey' => 'real-sms-key'],
        ]);

        $json = file_get_contents(base_path('tests/fixtures/teamsuite-club-backup-minimal.json'));
        $service = app(TeamSuiteBackupRestoreService::class);
        $parsed = app(TeamSuiteBackupParser::class)->parse($json);
        $service->ensureClubFromPayload($parsed);

        $settings = $club->fresh('settings')->settings;
        $this->assertSame('real-secret', $settings->smtp['password'] ?? null);
        $this->assertSame('real-sms-key', $settings->sms['apiKey'] ?? null);
    }

    public function test_full_restore_imports_athletes(): void
    {
        $json = file_get_contents(base_path('tests/fixtures/teamsuite-club-backup-minimal.json'));
        $parsed = app(TeamSuiteBackupParser::class)->parse($json);
        $counts = app(TeamSuiteBackupRestoreService::class)->restoreClub($parsed, 'merge');

        $this->assertSame(3, $counts['students']);
        $this->assertSame(1, $counts['activeAthletes']);
        $this->assertSame(1, $counts['activeAthletesInBackup']);
        $this->assertSame(1, $counts['deletedStudents']);
        $this->assertSame(2, Athlete::query()->withoutGlobalScopes()
            ->where('club_id', 'club-legacy-001')
            ->whereNull('deleted_at')
            ->count());
        $this->assertSame(1, Athlete::query()->withoutGlobalScopes()->withTrashed()
            ->where('club_id', 'club-legacy-001')
            ->where('legacy_id', 'a3-deleted')
            ->whereNotNull('deleted_at')
            ->count());
    }

    public function test_restore_reconciles_stale_athlete_status_on_merge(): void
    {
        $club = Club::query()->create([
            'id' => 'club-legacy-001',
            'name' => 'Existing',
            'slug' => 'existing',
            'is_active' => true,
        ]);

        Athlete::query()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'legacy_id' => 'a1',
            'first_name' => 'Νίκος',
            'last_name' => 'Δημητρίου',
            'status' => 'inactive',
        ]);

        $json = file_get_contents(base_path('tests/fixtures/teamsuite-club-backup-minimal.json'));
        $parsed = app(TeamSuiteBackupParser::class)->parse($json);
        $counts = app(TeamSuiteBackupRestoreService::class)->restoreClub($parsed, 'merge');

        $this->assertSame(1, $counts['activeAthletes']);
        $athlete = Athlete::query()->withoutGlobalScopes()
            ->where('club_id', 'club-legacy-001')
            ->where('legacy_id', 'a1')
            ->first();
        $this->assertNotNull($athlete);
        $this->assertSame('active', $athlete->status);
    }

    public function test_restore_normalizes_preferred_parent_email_from_teamsuite_camel_case(): void
    {
        $json = file_get_contents(base_path('tests/fixtures/teamsuite-club-backup-minimal.json'));
        $parsed = app(TeamSuiteBackupParser::class)->parse($json);
        app(TeamSuiteBackupRestoreService::class)->restoreClub($parsed, 'merge');

        $athlete = Athlete::query()->withoutGlobalScopes()
            ->where('club_id', 'club-legacy-001')
            ->where('legacy_id', 'a1')
            ->first();

        $this->assertNotNull($athlete);
        $meta = $athlete->meta ?? [];
        $this->assertSame('both', $meta['preferred_parent_email'] ?? null);
        $this->assertSame('father.dimitriou@example.com', $meta['father_email'] ?? null);
        $this->assertSame('mother.dimitriou@example.com', $meta['mother_email'] ?? null);
        $this->assertSame('Γιώργος', $meta['father_first_name'] ?? null);
        $this->assertArrayNotHasKey('preferredParentEmail', $meta);

        $profile = $athlete->toProfileArray();
        $this->assertSame('both', $profile['preferred_parent_email']);
        $this->assertSame(['father', 'mother'], PreferredNotifyEmail::targets($profile['preferred_parent_email']));
    }

    public function test_restore_imports_users_and_parent_links(): void
    {
        $json = file_get_contents(base_path('tests/fixtures/teamsuite-club-backup-minimal.json'));
        $parsed = app(TeamSuiteBackupParser::class)->parse($json);
        $counts = app(TeamSuiteBackupRestoreService::class)->restoreClub($parsed, 'merge');

        $this->assertSame(2, $counts['users']);
        $this->assertSame(1, $counts['parentLinks']);

        $parent = User::query()->where('email', 'parent.dimitriou@example.com')->first();
        $this->assertNotNull($parent);
        $this->assertSame('parent', $parent->clubs()->where('clubs.id', 'club-legacy-001')->first()?->pivot?->role);

        $athlete = Athlete::query()->withoutGlobalScopes()
            ->where('club_id', 'club-legacy-001')
            ->where('legacy_id', 'a1')
            ->first();
        $this->assertNotNull($athlete);

        $link = ParentAthleteLink::query()->withoutGlobalScopes()
            ->where('club_id', 'club-legacy-001')
            ->where('legacy_id', 'plink1')
            ->first();
        $this->assertNotNull($link);
        $this->assertSame($parent->id, $link->parent_user_id);
        $this->assertSame($athlete->id, $link->athlete_id);
    }

    public function test_restore_links_athletes_to_classes(): void
    {
        $json = file_get_contents(base_path('tests/fixtures/teamsuite-club-backup-minimal.json'));
        $parsed = app(TeamSuiteBackupParser::class)->parse($json);
        app(TeamSuiteBackupRestoreService::class)->restoreClub($parsed, 'merge');

        $class = AcademyClass::query()->withoutGlobalScopes()
            ->where('club_id', 'club-legacy-001')
            ->where('legacy_id', 'cl1')
            ->first();
        $this->assertNotNull($class);
        $this->assertSame(2, $class->athletes()->count());

        $athlete = Athlete::query()->withoutGlobalScopes()
            ->where('club_id', 'club-legacy-001')
            ->where('legacy_id', 'a1')
            ->first();
        $this->assertNotNull($athlete);
        $this->assertTrue($athlete->classes->contains('id', $class->id));
        $this->assertSame([$class->id], $athlete->meta['class_ids'] ?? null);
    }

    public function test_restore_maps_legacy_receipts_and_size_chart(): void
    {
        $json = file_get_contents(base_path('tests/fixtures/teamsuite-club-backup-minimal.json'));
        $parsed = app(TeamSuiteBackupParser::class)->parse($json);
        $counts = app(TeamSuiteBackupRestoreService::class)->restoreClub($parsed, 'merge');

        $this->assertSame(2, $counts['receiptNumberRanges']);
        $this->assertSame(2, $counts['receiptIssues']);
        $this->assertSame(1, $counts['sizeChart']);

        $firstRange = ReceiptSeries::query()->withoutGlobalScopes()
            ->where('club_id', 'club-legacy-001')
            ->where('legacy_id', 'rrp1')
            ->first();
        $this->assertNotNull($firstRange);
        $this->assertSame('Γ', $firstRange->series);
        $this->assertSame(1, $firstRange->range_from);
        $this->assertSame(50, $firstRange->range_to);
        $this->assertSame(44, $firstRange->next_number);

        $secondRange = ReceiptSeries::query()->withoutGlobalScopes()
            ->where('club_id', 'club-legacy-001')
            ->where('legacy_id', 'rrp2')
            ->first();
        $this->assertNotNull($secondRange);
        $this->assertSame(51, $secondRange->range_from);
        $this->assertSame(100, $secondRange->range_to);

        $transaction = AthleteTransaction::query()->withoutGlobalScopes()
            ->where('club_id', 'club-legacy-001')
            ->where('legacy_id', 't1')
            ->first();
        $this->assertNotNull($transaction);

        $issued = ReceiptIssue::query()->withoutGlobalScopes()
            ->where('club_id', 'club-legacy-001')
            ->where('legacy_id', 'ris1')
            ->first();
        $this->assertNotNull($issued);
        $this->assertSame('Γ', $issued->series);
        $this->assertSame(1, $issued->number);
        $this->assertSame('issued', $issued->status);
        $this->assertSame($firstRange->id, $issued->receipt_series_id);
        $this->assertSame($transaction->id, $issued->athlete_transaction_id);
        $this->assertSame('2026-09-14 16:19:07', $issued->issued_at?->format('Y-m-d H:i:s'));
        $this->assertSame(40, $issued->meta['amount'] ?? null);
        $this->assertSame('ΔΗΜΗΤΡΙΟΥ ΝΙΚΟΣ', $issued->meta['receivedFrom'] ?? null);
        $this->assertSame('ΕΓΓΡΑΦΗ', $issued->meta['reason'] ?? null);
        $this->assertSame('subscription', $issued->meta['kind'] ?? null);
        $this->assertNotEmpty($issued->meta['emailedAt'] ?? null);
        $this->assertNull($issued->cancelled_at);

        $voided = ReceiptIssue::query()->withoutGlobalScopes()
            ->where('club_id', 'club-legacy-001')
            ->where('legacy_id', 'ris2')
            ->first();
        $this->assertNotNull($voided);
        $this->assertSame('cancelled', $voided->status);
        $this->assertSame($firstRange->id, $voided->receipt_series_id);
        $this->assertNotNull($voided->cancelled_at);
        $this->assertSame(105, $voided->meta['amount'] ?? null);
        $this->assertSame('ΠΑΠΑΔΟΠΟΥΛΟΥ ΜΑΡΙΑ', $voided->meta['receivedFrom'] ?? null);
        $this->assertSame('Διαγραφή', $voided->meta['voidReason'] ?? null);

        $sizeChart = SizeChart::query()->withoutGlobalScopes()
            ->where('club_id', 'club-legacy-001')
            ->first();
        $this->assertNotNull($sizeChart);
        $this->assertSame(['XS', 'S', 'M', 'L', 'XL'], $sizeChart->chart['kids'] ?? null);
        $this->assertSame([], $sizeChart->chart['men'] ?? null);
        $this->assertSame([], $sizeChart->chart['women'] ?? null);
    }

    public function test_restore_maps_legacy_trainings_and_attendance(): void
    {
        $json = file_get_contents(base_path('tests/fixtures/teamsuite-club-backup-minimal.json'));
        $parsed = app(TeamSuiteBackupParser::class)->parse($json);
        $counts = app(TeamSuiteBackupRestoreService::class)->restoreClub($parsed, 'merge');

        $this->assertSame(2, $counts['trainings']);
        $this->assertSame(2, $counts['attendance']);
        $this->assertSame(1, $counts['schedule']);

        $class = AcademyClass::query()->withoutGlobalScopes()
            ->where('club_id', 'club-legacy-001')
            ->where('legacy_id', 'cl1')
            ->first();
        $this->assertNotNull($class);

        $training = Training::query()->withoutGlobalScopes()
            ->where('club_id', 'club-legacy-001')
            ->where('legacy_id', 'tr1')
            ->first();
        $this->assertNotNull($training);
        $this->assertSame($class->id, $training->academy_class_id);
        $this->assertSame('Κλειστό Α', $training->title);
        $this->assertSame('2026-09-10 18:00:00', $training->starts_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-10 19:30:00', $training->ends_at?->format('Y-m-d H:i:s'));
        $this->assertSame('Μπάσκετ', $training->meta['sport'] ?? null);
        $this->assertSame('U12', $training->meta['class_name'] ?? null);

        $slot = ScheduleSlot::query()->withoutGlobalScopes()
            ->where('club_id', 'club-legacy-001')
            ->where('legacy_id', 'sch1')
            ->first();
        $this->assertNotNull($slot);
        $this->assertSame(1, $slot->weekday);
        $this->assertSame($class->id, $slot->academy_class_id);
        $this->assertSame('Κλειστό Α', $slot->title);

        $athlete = Athlete::query()->withoutGlobalScopes()
            ->where('club_id', 'club-legacy-001')
            ->where('legacy_id', 'a1')
            ->first();
        $this->assertNotNull($athlete);

        $attendance = Attendance::query()->withoutGlobalScopes()
            ->where('club_id', 'club-legacy-001')
            ->where('legacy_id', 'att1')
            ->first();
        $this->assertNotNull($attendance);
        $this->assertSame($athlete->id, $attendance->athlete_id);
        $this->assertSame('present', $attendance->status);
        $this->assertSame('2026-09-10', $attendance->attended_on?->format('Y-m-d'));
        $this->assertSame($class->id, $attendance->meta['class_id'] ?? null);
        $this->assertSame($training->id, $attendance->training_id);

        $absent = Attendance::query()->withoutGlobalScopes()
            ->where('club_id', 'club-legacy-001')
            ->where('legacy_id', 'att2')
            ->first();
        $this->assertNotNull($absent);
        $this->assertSame('excused', $absent->status);
        $this->assertSame('sick', $absent->meta['absence_reason'] ?? null);
    }

    public function test_restore_maps_legacy_announcements(): void
    {
        $json = file_get_contents(base_path('tests/fixtures/teamsuite-club-backup-minimal.json'));
        $parsed = app(TeamSuiteBackupParser::class)->parse($json);
        $counts = app(TeamSuiteBackupRestoreService::class)->restoreClub($parsed, 'merge');

        $this->assertSame(1, $counts['announcements']);

        $announcement = Announcement::query()->withoutGlobalScopes()
            ->where('club_id', 'club-legacy-001')
            ->where('legacy_id', 'ann1')
            ->first();
        $this->assertNotNull($announcement);
        $this->assertSame('Νέος τρόπος ενημέρωσης για τους αγώνες', $announcement->title);
        $this->assertStringContainsString('Αγαπητοί γονείς', $announcement->body);
        $this->assertNotNull($announcement->published_at);
        $this->assertSame(
            ['parent', 'coach', 'athlete', 'staff'],
            $announcement->audience['roles'] ?? null
        );
        $this->assertNotEmpty($announcement->audience['class_ids'] ?? []);
        $this->assertSame(34, $announcement->emailStats()['sentCount'] ?? null);
        $this->assertSame(2, $announcement->emailStats()['failedCount'] ?? null);
        $this->assertSame('https://example.com/announcement.jpg', $announcement->imageUrl());
        $this->assertSame('ΑΚΑΔΗΜΙΑ ΑΠΟΛΛΩΝ ΠΑΤΡΩΝ', $announcement->creatorDisplayName());
    }

    public function test_restore_switches_active_club_for_operator(): void
    {
        $demo = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Demo',
            'slug' => 'demo-switch',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $demo->id,
        ]);
        $user->clubs()->attach($demo->id, ['role' => 'admin', 'is_active' => true]);

        $json = file_get_contents(base_path('tests/fixtures/teamsuite-club-backup-minimal.json'));
        $file = UploadedFile::fake()->createWithContent('club.json', $json);

        $this->actingAs($user)
            ->post(route('admin.settings.backup.preview'), ['backup_file' => $file])
            ->assertRedirect();

        $backupId = session('backup_dry_run')['backupId'] ?? null;
        $this->assertNotEmpty($backupId);

        $this->actingAs($user)
            ->withSession([
                'backup_pending_path' => session('backup_pending_path'),
                'backup_dry_run' => session('backup_dry_run'),
            ])
            ->post(route('admin.settings.backup.restore'), [
                'backup_id' => $backupId,
                'mode' => 'merge',
            ])
            ->assertRedirect(route('admin.settings.backup.index'));

        $user->refresh();
        $this->assertSame('club-legacy-001', $user->active_club_id);
        $this->assertTrue($user->clubs()->where('clubs.id', 'club-legacy-001')->exists());
        $this->assertSame('Α.Σ. Απόλλων Πατρών', $user->activeClub?->name);
    }

    public function test_backup_preview_ui_endpoint(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Demo',
            'slug' => 'demo',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
            'is_platform_admin' => false,
        ]);

        $user->clubs()->attach($club->id, [
            'role' => 'admin',
            'is_active' => true,
        ]);

        $json = file_get_contents(base_path('tests/fixtures/teamsuite-club-backup-minimal.json'));
        $file = UploadedFile::fake()->createWithContent('TeamSuite-Demo-2026-09-13-00-26 (2).json', $json);

        $response = $this->actingAs($user)->post(route('admin.settings.backup.preview'), [
            'backup_file' => $file,
        ]);

        $response->assertRedirect(route('admin.settings.backup.index'));
        $response->assertSessionHas('backup_dry_run');
        $dryRun = session('backup_dry_run');
        $this->assertSame(3, $dryRun['counts']['students']);
        $this->assertFalse($dryRun['clubExists']);
    }
}
