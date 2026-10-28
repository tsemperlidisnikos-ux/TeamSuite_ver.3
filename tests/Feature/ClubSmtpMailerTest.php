<?php

namespace Tests\Feature;

use App\Jobs\SendAnnouncementEmailsJob;
use App\Models\Announcement;
use App\Models\Club;
use App\Models\ClubSetting;
use App\Models\User;
use App\Services\Mail\ClubSmtpMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ClubSmtpMailerTest extends TestCase
{
    use RefreshDatabase;

    public function test_configure_applies_club_smtp_settings(): void
    {
        ['club' => $club] = $this->seedClubWithSmtp();

        app(ClubSmtpMailer::class)->configure($club->id);

        $this->assertSame('smtp', Config::get('mail.default'));
        $this->assertSame('mail.example.com', Config::get('mail.mailers.smtp.host'));
        $this->assertSame(587, Config::get('mail.mailers.smtp.port'));
        $this->assertSame('noreply@example.com', Config::get('mail.from.address'));
        $this->assertSame('secret-pass', Config::get('mail.mailers.smtp.password'));
    }

    public function test_configure_requires_password_when_auth_enabled(): void
    {
        ['club' => $club] = $this->seedClubWithSmtp(['password' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('username/password');

        app(ClubSmtpMailer::class)->configure($club->id);
    }

    public function test_announcement_job_marks_failed_when_smtp_incomplete(): void
    {
        ['club' => $club] = $this->seedClubWithSmtp([
            'password' => '',
            'fromEmail' => '',
        ]);

        $announcement = Announcement::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'title' => 'Test',
            'body' => 'Body',
            'audience' => ['roles' => ['parent']],
            'published_at' => now(),
            'meta' => [],
        ]);

        Mail::fake();

        (new SendAnnouncementEmailsJob(
            $club->id,
            $announcement->id,
            'Test',
            'Body',
            ['parent@example.com'],
        ))->handle(app(ClubSmtpMailer::class));

        Mail::assertNothingSent();

        $stats = $announcement->fresh()->emailStats();
        $this->assertSame(0, $stats['sentCount'] ?? null);
        $this->assertSame(1, $stats['failedCount'] ?? null);
        $this->assertNotSame('', (string) ($stats['recipients'][0]['error'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $smtpOverrides
     * @return array{club: Club, user: User}
     */
    private function seedClubWithSmtp(array $smtpOverrides = []): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Mail Club',
            'slug' => 'mail-'.Str::random(5),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $user->clubs()->attach($club->id, ['role' => 'admin', 'is_active' => true]);

        ClubSetting::query()->create([
            'club_id' => $club->id,
            'smtp' => array_merge([
                'enabled' => true,
                'host' => 'mail.example.com',
                'port' => '587',
                'username' => 'smtp-user',
                'password' => 'secret-pass',
                'fromName' => 'Mail Club',
                'fromEmail' => 'noreply@example.com',
                'security' => 'starttls',
                'requireAuth' => true,
            ], $smtpOverrides),
        ]);

        return compact('club', 'user');
    }
}
