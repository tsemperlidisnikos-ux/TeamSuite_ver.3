<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubSetting;
use App\Models\User;
use App\Services\Mail\ClubSmtpMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClubSmtpTestEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_send_test_email_with_saved_smtp(): void
    {
        ['club' => $club, 'user' => $user] = $this->seedClubWithSmtp();

        $outbound = \Mockery::mock(\Illuminate\Contracts\Mail\Mailer::class);
        $outbound->shouldReceive('raw')->once()->andReturnNull();

        $this->mock(ClubSmtpMailer::class, function ($mock) use ($outbound) {
            $mock->shouldReceive('mailer')->once()->andReturn($outbound);
        });

        $this->actingAs($user)
            ->from(route('admin.settings.index', ['tab' => 'email']))
            ->post(route('admin.settings.email.test'), [
                'to' => 'check@example.com',
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'email']))
            ->assertSessionHas('success');
    }

    public function test_test_email_fails_when_smtp_incomplete(): void
    {
        ['user' => $user] = $this->seedClubWithSmtp([
            'fromEmail' => '',
            'password' => '',
        ]);

        $this->actingAs($user)
            ->from(route('admin.settings.index', ['tab' => 'email']))
            ->post(route('admin.settings.email.test'), [
                'to' => 'check@example.com',
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'email']))
            ->assertSessionHas('error');
    }

    public function test_test_email_requires_valid_recipient(): void
    {
        ['user' => $user] = $this->seedClubWithSmtp();

        $this->actingAs($user)
            ->post(route('admin.settings.email.test'), [
                'to' => 'not-an-email',
            ])
            ->assertSessionHasErrors('to');
    }

    /**
     * @param  array<string, mixed>  $smtpOverrides
     * @return array{club: Club, user: User}
     */
    private function seedClubWithSmtp(array $smtpOverrides = []): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'SMTP Club',
            'slug' => 'smtp-'.Str::random(5),
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
                'fromName' => 'SMTP Club',
                'fromEmail' => 'noreply@example.com',
                'security' => 'starttls',
                'requireAuth' => true,
            ], $smtpOverrides),
        ]);

        return compact('club', 'user');
    }
}
