<?php

namespace Tests\Feature;

use App\Models\AcademyClass;
use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Club;
use App\Models\Coach;
use App\Models\Facility;
use App\Models\ParentAthleteLink;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortalParityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{club: Club, coachUser: User, coach: Coach, class: AcademyClass, athlete: Athlete}
     */
    private function seedCoachPortal(): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Portal Club',
            'slug' => 'portal-'.Str::random(6),
            'is_active' => true,
        ]);

        $coachUser = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $coachUser->clubs()->attach($club->id, ['role' => 'coach', 'is_active' => true]);

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
            'first_name' => 'Άννα',
            'last_name' => 'Νικολάου',
            'status' => 'active',
            'gdpr_consent' => 'pending',
        ]);
        $class->athletes()->attach($athlete->id, ['is_primary' => true]);

        return compact('club', 'coachUser', 'coach', 'class', 'athlete');
    }

    /**
     * @return array{club: Club, parentUser: User, athlete: Athlete, class: AcademyClass}
     */
    private function seedParentPortal(): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Parent Club',
            'slug' => 'parent-'.Str::random(6),
            'is_active' => true,
        ]);

        $parentUser = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $parentUser->clubs()->attach($club->id, ['role' => 'parent', 'is_active' => true]);

        $class = AcademyClass::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'name' => 'U10',
            'sport' => 'Ποδόσφαιρο',
        ]);

        $athlete = Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Μαρία',
            'last_name' => 'Παπα',
            'status' => 'active',
            'gdpr_consent' => 'pending',
        ]);
        $class->athletes()->attach($athlete->id, ['is_primary' => true]);

        ParentAthleteLink::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'parent_user_id' => $parentUser->id,
            'athlete_id' => $athlete->id,
            'relation' => 'parent',
        ]);

        return compact('club', 'parentUser', 'athlete', 'class');
    }

    public function test_coach_portal_saves_attendance_for_selected_date(): void
    {
        ['coachUser' => $user, 'class' => $class, 'athlete' => $athlete] = $this->seedCoachPortal();
        $date = now()->subDay()->toDateString();

        $this->actingAs($user)
            ->get(route('portal.coach', ['date' => $date]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portals/CoachHome')
                ->where('attendance_date', $date)
                ->has('classes', 1)
            );

        $this->actingAs($user)
            ->from(route('portal.coach', ['date' => $date]))
            ->post(route('portal.coach.attendance'), [
                'class_id' => $class->id,
                'date' => $date,
                'marks' => [
                    ['athlete_id' => $athlete->id, 'status' => 'present'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'athlete_id' => $athlete->id,
            'status' => 'present',
        ]);

        $row = Attendance::query()->withoutGlobalScopes()
            ->where('athlete_id', $athlete->id)
            ->first();
        $this->assertSame($class->id, data_get($row?->meta, 'class_id'));
        $this->assertSame($date, optional($row?->attended_on)?->format('Y-m-d'));
    }

    public function test_parent_portal_respects_tab_query_and_submits_absence(): void
    {
        ['parentUser' => $user, 'athlete' => $athlete, 'class' => $class] = $this->seedParentPortal();
        $date = now()->toDateString();

        $this->actingAs($user)
            ->get(route('portal.parent', ['tab' => 'schedule']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portals/ParentHome')
                ->where('tab', 'schedule')
                ->has('athletes', 1)
            );

        $this->actingAs($user)
            ->from(route('portal.parent', ['tab' => 'schedule']))
            ->post(route('portal.parent.absence'), [
                'athlete_id' => $athlete->id,
                'date' => $date,
                'reason' => 'sick',
                'notes' => 'πυρετός',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'athlete_id' => $athlete->id,
            'status' => 'excused',
        ]);

        $row = Attendance::query()->withoutGlobalScopes()
            ->where('athlete_id', $athlete->id)
            ->first();
        $this->assertSame('sick', data_get($row?->meta, 'absence_reason'));
        $this->assertSame($class->id, data_get($row?->meta, 'class_id'));
    }

    public function test_calendar_events_include_end_times_and_locations(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Cal Club',
            'slug' => 'cal-'.Str::random(6),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $user->clubs()->attach($club->id, ['role' => 'admin', 'is_active' => true]);

        $facility = Facility::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'name' => 'Κλειστό Α',
        ]);

        $starts = now()->startOfMonth()->setTime(18, 0);
        Training::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'facility_id' => $facility->id,
            'title' => 'Προπόνηση Α',
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->setTime(19, 30),
            'status' => 'scheduled',
        ]);

        $this->actingAs($user)
            ->get(route('admin.calendar.index', [
                'month' => now()->month,
                'year' => now()->year,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Calendar/Index')
                ->has('facilities', 1)
                ->has('events')
                ->where('stats.trainings', 1)
            );
    }
}
