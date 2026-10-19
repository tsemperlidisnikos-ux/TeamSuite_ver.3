<?php

namespace Tests\Feature;

use App\Jobs\ClubBackupJob;
use App\Jobs\SendAnnouncementEmailsJob;
use App\Models\Announcement;
use App\Models\Athlete;
use App\Models\Club;
use App\Models\ClubBackup;
use App\Models\User;
use App\Services\Backup\TeamSuiteBackupParser;
use App\Services\Backup\TeamSuiteBackupRestoreService;
use App\Services\Backup\TeamSuiteClubExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClubExportAndAnnouncementResendTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{club: Club, user: User}
     */
    private function seedAdmin(): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Export Club',
            'slug' => 'export-'.Str::random(5),
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

    public function test_download_exports_full_club_json_envelope(): void
    {
        ['club' => $club, 'user' => $user] = $this->seedAdmin();

        Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'legacy_id' => 'student-1',
            'first_name' => 'Νίκος',
            'last_name' => 'Παπαδόπουλος',
            'status' => 'active',
            'email' => 'nikos@example.com',
        ]);

        $response = $this->actingAs($user)->get(route('admin.settings.backup.download'));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/json; charset=UTF-8');

        $payload = json_decode($response->streamedContent(), true);
        $this->assertIsArray($payload);
        $this->assertSame('club', $payload['scope']);
        $this->assertSame($club->id, $payload['sourceClubId']);
        $this->assertArrayHasKey('appData', $payload);
        $this->assertArrayHasKey('appDataByClub', $payload);
        $this->assertSame($club->id, $payload['clubs'][0]['id'] ?? null);
        $this->assertSame('', $payload['clubs'][0]['smtp']['password'] ?? 'missing');
        $this->assertCount(1, $payload['appData']['students'] ?? []);
        $this->assertSame('student-1', $payload['appData']['students'][0]['id']);
        $this->assertSame('Παπαδόπουλος', $payload['appData']['students'][0]['lastName']);
        $this->assertSame($payload['appData'], $payload['appDataByClub'][$club->id] ?? null);
    }

    public function test_exporter_roundtrips_restored_fixture_students(): void
    {
        $json = file_get_contents(base_path('tests/fixtures/teamsuite-club-backup-minimal.json'));
        $parsed = app(TeamSuiteBackupParser::class)->parse($json);
        app(TeamSuiteBackupRestoreService::class)->restoreClub($parsed, 'merge');

        $club = Club::query()->findOrFail('club-legacy-001');
        $payload = app(TeamSuiteClubExporter::class)->export($club);

        $this->assertSame('club', $payload['scope']);
        $this->assertGreaterThanOrEqual(2, count($payload['appData']['students'] ?? []));
        $studentIds = collect($payload['appData']['students'])->pluck('id')->all();
        $this->assertContains('a1', $studentIds);
        $this->assertArrayHasKey('announcements', $payload['appData']);
        $this->assertArrayHasKey('transactions', $payload['appData']);
    }

    public function test_scheduled_backup_job_writes_full_export(): void
    {
        Storage::fake('local');
        ['club' => $club] = $this->seedAdmin();

        Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Μαρία',
            'last_name' => 'Ιωάννου',
            'status' => 'active',
        ]);

        (new ClubBackupJob)->handle(app(TeamSuiteClubExporter::class));

        $backup = ClubBackup::query()->where('club_id', $club->id)->where('source', 'scheduled')->first();
        $this->assertNotNull($backup);
        $this->assertSame('exported', $backup->status);
        $this->assertTrue(Storage::disk('local')->exists($backup->storage_path));

        $payload = json_decode(Storage::disk('local')->get($backup->storage_path), true);
        $this->assertSame('club', $payload['scope'] ?? null);
        $this->assertCount(1, $payload['appData']['students'] ?? []);
        $this->assertArrayNotHasKey('note', $payload);
    }

    public function test_resend_failed_queues_only_failed_emails(): void
    {
        Queue::fake();
        ['club' => $club, 'user' => $user] = $this->seedAdmin();

        $announcement = Announcement::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'created_by' => $user->id,
            'title' => 'Τεστ',
            'body' => 'Μήνυμα',
            'audience' => ['roles' => ['parent']],
            'published_at' => now(),
            'meta' => [
                'email_stats' => [
                    'sentCount' => 1,
                    'failedCount' => 2,
                    'recipients' => [
                        ['email' => 'ok@example.com', 'status' => 'sent'],
                        ['email' => 'fail1@example.com', 'status' => 'failed', 'error' => 'smtp'],
                        ['email' => 'fail2@example.com', 'status' => 'failed', 'error' => 'bounce'],
                    ],
                ],
            ],
        ]);

        $this->actingAs($user)
            ->post(route('admin.announcements.resend-failed', $announcement->id))
            ->assertRedirect();

        Queue::assertPushed(SendAnnouncementEmailsJob::class, function (SendAnnouncementEmailsJob $job) use ($announcement, $club) {
            $emails = $job->emails;
            sort($emails);

            return $job->announcementId === $announcement->id
                && $job->clubId === $club->id
                && $job->mergeStats === true
                && $emails === ['fail1@example.com', 'fail2@example.com'];
        });
    }

    public function test_resend_failed_with_no_failures_returns_error(): void
    {
        Queue::fake();
        ['club' => $club, 'user' => $user] = $this->seedAdmin();

        $announcement = Announcement::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'title' => 'Χωρίς αποτυχίες',
            'body' => 'Μήνυμα',
            'audience' => ['roles' => ['parent']],
            'published_at' => now(),
            'meta' => [
                'email_stats' => [
                    'sentCount' => 1,
                    'failedCount' => 0,
                    'recipients' => [
                        ['email' => 'ok@example.com', 'status' => 'sent'],
                    ],
                ],
            ],
        ]);

        $this->actingAs($user)
            ->post(route('admin.announcements.resend-failed', $announcement->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        Queue::assertNothingPushed();
    }

    public function test_send_job_merges_resend_stats(): void
    {
        ['club' => $club] = $this->seedAdmin();

        $announcement = Announcement::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'title' => 'Merge',
            'body' => 'Body',
            'audience' => ['roles' => ['parent']],
            'published_at' => now(),
            'meta' => [
                'email_stats' => [
                    'sentCount' => 1,
                    'failedCount' => 1,
                    'recipients' => [
                        ['email' => 'ok@example.com', 'status' => 'sent'],
                        ['email' => 'fail@example.com', 'status' => 'failed', 'error' => 'smtp'],
                    ],
                ],
            ],
        ]);

        Mail::fake();

        $job = new SendAnnouncementEmailsJob(
            $club->id,
            $announcement->id,
            'Merge',
            'Body',
            ['fail@example.com'],
            null,
            true,
        );
        $job->handle();

        $stats = $announcement->fresh()->emailStats();
        $this->assertSame(2, $stats['sentCount'] ?? null);
        $this->assertSame(0, $stats['failedCount'] ?? null);
        $statuses = collect($stats['recipients'] ?? [])->pluck('status', 'email');
        $this->assertSame('sent', $statuses['ok@example.com'] ?? null);
        $this->assertSame('sent', $statuses['fail@example.com'] ?? null);
    }
}
