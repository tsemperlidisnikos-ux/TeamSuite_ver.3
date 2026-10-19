<?php

namespace Tests\Feature;

use App\Jobs\SendHealthCardNoticeEmailsJob;
use App\Models\Athlete;
use App\Models\Club;
use App\Models\RegistrationApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AthletesPeopleParityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{club: Club, user: User}
     */
    private function seedClubUser(): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'People Club',
            'slug' => 'people-'.Str::random(6),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
            'email' => 'staff@example.com',
        ]);
        $user->clubs()->attach($club->id, ['role' => 'admin', 'is_active' => true]);

        return compact('club', 'user');
    }

    public function test_update_pending_application_fields(): void
    {
        ['club' => $club, 'user' => $user] = $this->seedClubUser();

        $app = RegistrationApplication::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'type' => 'full',
            'status' => 'pending',
            'first_name' => 'Μαρία',
            'last_name' => 'Ιωάννου',
            'email' => 'maria@example.com',
            'gdpr_accepted' => true,
            'form_snapshot' => [],
        ]);

        $this->actingAs($user)
            ->patch(route('admin.athletes.applications.update', $app->id), [
                'first_name' => 'Ελένη',
                'last_name' => 'Παπαδοπούλου',
                'email' => 'eleni@example.com',
                'guardian_name' => 'Γονέας Παπαδόπουλος',
                'guardian_phone' => '6900000001',
                'type' => 'trial',
                'notes' => 'Δοκιμαστική εγγραφή',
            ])
            ->assertRedirect();

        $app->refresh();
        $this->assertSame('Ελένη', $app->first_name);
        $this->assertSame('Παπαδοπούλου', $app->last_name);
        $this->assertSame('trial', $app->type);
        $this->assertSame('Γονέας Παπαδόπουλος', $app->form_snapshot['guardian_name']);
        $this->assertSame('Δοκιμαστική εγγραφή', $app->form_snapshot['notes']);
    }

    public function test_health_card_reminders_dispatch_job(): void
    {
        Bus::fake();

        ['club' => $club, 'user' => $user] = $this->seedClubUser();

        Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Νίκος',
            'last_name' => 'Παπαδόπουλος',
            'status' => 'active',
            'email' => 'parent@example.com',
            'health_card_expires' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($user)
            ->post(route('admin.athletes.health-reminders'))
            ->assertRedirect()
            ->assertSessionHas('success');

        Bus::assertDispatched(SendHealthCardNoticeEmailsJob::class, function (SendHealthCardNoticeEmailsJob $job) use ($club) {
            return $job->clubId === $club->id
                && count($job->recipients) === 1
                && $job->recipients[0]['email'] === 'parent@example.com';
        });
    }
}
