<?php

namespace Tests\Feature;

use App\Jobs\SendAnnouncementEmailsJob;
use App\Models\AcademyClass;
use App\Models\Announcement;
use App\Models\Athlete;
use App\Models\Club;
use App\Models\Coach;
use App\Models\StaffMember;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoachCanSendAnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{club: Club, coachUser: User, coach: Coach, class: AcademyClass, athlete: Athlete}
     */
    private function seedCoachWithClass(): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Coach Announcements Club',
            'slug' => 'coach-ann-'.Str::random(6),
            'is_active' => true,
        ]);

        $coachUser = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $coachUser->clubs()->attach($club->id, [
            'role' => Roles::COACH,
            'is_active' => true,
            'permissions' => json_encode(['dashboard', 'attendance', 'classes']),
        ]);

        $coach = Coach::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'user_id' => $coachUser->id,
            'email' => $coachUser->email,
            'first_name' => 'Νίκος',
            'last_name' => 'Προπονητής',
            'is_active' => true,
        ]);

        $class = AcademyClass::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'coach_id' => $coach->id,
            'name' => 'U12',
            'sport' => 'Μπάσκετ',
        ]);

        $athlete = Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Μαρία',
            'last_name' => 'Παπα',
            'email' => 'parent-of-maria@example.com',
            'status' => 'active',
            'gdpr_consent' => 'pending',
        ]);
        $athlete->classes()->attach($class->id);

        return compact('club', 'coachUser', 'coach', 'class', 'athlete');
    }

    public function test_coach_permissions_always_include_announcements(): void
    {
        ['coachUser' => $user, 'club' => $club] = $this->seedCoachWithClass();

        $this->assertContains('announcements', $user->permissionsInClub($club));
    }

    public function test_coach_can_send_announcement_from_portal(): void
    {
        Queue::fake();
        ['coachUser' => $user, 'class' => $class, 'athlete' => $athlete] = $this->seedCoachWithClass();

        $this->actingAs($user)
            ->post(route('portal.coach.announcements.store'), [
                'title' => 'Ακύρωση προπόνησης',
                'body' => 'Η σημερινή προπόνηση ακυρώνεται.',
                'class_ids' => [$class->id],
                'send_email' => true,
                'send_sms' => false,
                'send_push' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('announcements', [
            'title' => 'Ακύρωση προπόνησης',
            'created_by' => $user->id,
        ]);

        Queue::assertPushed(SendAnnouncementEmailsJob::class, function (SendAnnouncementEmailsJob $job) use ($athlete) {
            return in_array($athlete->email, $job->emails, true);
        });
    }

    public function test_coach_cannot_target_another_coachs_class(): void
    {
        Queue::fake();
        ['club' => $club, 'coachUser' => $user] = $this->seedCoachWithClass();

        $otherCoach = Coach::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Άλλος',
            'last_name' => 'Προπονητής',
            'is_active' => true,
        ]);

        $otherClass = AcademyClass::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'coach_id' => $otherCoach->id,
            'name' => 'Other',
            'sport' => 'Μπάσκετ',
        ]);

        $this->actingAs($user)
            ->from(route('portal.coach'))
            ->post(route('portal.coach.announcements.store'), [
                'title' => 'Μόνο δικά μου',
                'body' => 'Σώμα',
                'class_ids' => [$otherClass->id],
                'send_email' => false,
            ])
            ->assertRedirect(route('portal.coach'))
            ->assertSessionHasErrors('class_ids');

        $this->assertNull(
            Announcement::query()->withoutGlobalScopes()
                ->where('title', 'Μόνο δικά μου')
                ->first()
        );
    }

    public function test_coach_can_target_athlete_coach_and_staff(): void
    {
        Queue::fake();
        ['club' => $club, 'coachUser' => $user, 'coach' => $coach, 'athlete' => $athlete] = $this->seedCoachWithClass();

        $otherCoach = Coach::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Έλενα',
            'last_name' => 'Συνάδελφος',
            'email' => 'other-coach@example.com',
            'is_active' => true,
        ]);

        $staff = StaffMember::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Άννα',
            'last_name' => 'Γραμματεία',
            'email' => 'staff@example.com',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('portal.coach.announcements.store'), [
                'title' => 'Μεικτοί παραλήπτες',
                'body' => 'Ενημέρωση',
                'athlete_ids' => [$athlete->id],
                'coach_ids' => [$otherCoach->id],
                'staff_ids' => [$staff->id],
                'send_email' => true,
                'send_sms' => false,
                'send_push' => false,
            ])
            ->assertRedirect();

        $row = Announcement::query()->withoutGlobalScopes()
            ->where('title', 'Μεικτοί παραλήπτες')
            ->first();

        $this->assertNotNull($row);
        $this->assertContains($athlete->id, $row->audience['athlete_ids'] ?? []);
        $this->assertContains($otherCoach->id, $row->audience['coach_ids'] ?? []);
        $this->assertContains($staff->id, $row->audience['staff_ids'] ?? []);
        $this->assertContains('athlete', $row->audience['roles'] ?? []);
        $this->assertContains('coach', $row->audience['roles'] ?? []);
        $this->assertContains('staff', $row->audience['roles'] ?? []);
        $this->assertNotContains($coach->id, $row->audience['coach_ids'] ?? []);

        Queue::assertPushed(SendAnnouncementEmailsJob::class, function (SendAnnouncementEmailsJob $job) use ($athlete, $otherCoach, $staff) {
            return in_array($athlete->email, $job->emails, true)
                && in_array($otherCoach->email, $job->emails, true)
                && in_array($staff->email, $job->emails, true);
        });
    }

    public function test_coach_cannot_target_athlete_outside_own_classes(): void
    {
        Queue::fake();
        ['club' => $club, 'coachUser' => $user] = $this->seedCoachWithClass();

        $otherCoach = Coach::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Άλλος',
            'last_name' => 'Προπονητής',
            'is_active' => true,
        ]);

        $otherClass = AcademyClass::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'coach_id' => $otherCoach->id,
            'name' => 'Other',
            'sport' => 'Μπάσκετ',
        ]);

        $foreignAthlete = Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Ξένος',
            'last_name' => 'Αθλητής',
            'email' => 'foreign-athlete@example.com',
            'status' => 'active',
            'gdpr_consent' => 'pending',
        ]);
        $foreignAthlete->classes()->attach($otherClass->id);

        $this->actingAs($user)
            ->from(route('portal.coach'))
            ->post(route('portal.coach.announcements.store'), [
                'title' => 'Ξένος αθλητής',
                'body' => 'Σώμα',
                'athlete_ids' => [$foreignAthlete->id],
                'send_email' => false,
            ])
            ->assertRedirect(route('portal.coach'))
            ->assertSessionHasErrors('class_ids');

        $this->assertNull(
            Announcement::query()->withoutGlobalScopes()
                ->where('title', 'Ξένος αθλητής')
                ->first()
        );
    }
}
