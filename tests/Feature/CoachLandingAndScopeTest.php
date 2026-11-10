<?php

namespace Tests\Feature;

use App\Models\AcademyClass;
use App\Models\Club;
use App\Models\Coach;
use App\Models\Training;
use App\Models\User;
use App\Support\ClubHome;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CoachLandingAndScopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *     club: Club,
     *     coachUser: User,
     *     coach: Coach,
     *     ownClass: AcademyClass,
     *     otherClass: AcademyClass,
     *     ownTraining: Training,
     *     otherTraining: Training
     * }
     */
    private function seedTwoCoachClasses(): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Coach Scope Club',
            'slug' => 'coach-scope-'.Str::random(6),
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
            'permissions' => json_encode([
                'dashboard',
                'trainings',
                'attendance',
                'classes',
                'athletes',
            ]),
        ]);

        $otherUser = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $otherUser->clubs()->attach($club->id, [
            'role' => Roles::COACH,
            'is_active' => true,
            'permissions' => json_encode(['dashboard', 'trainings', 'attendance']),
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

        $otherCoach = Coach::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'user_id' => $otherUser->id,
            'email' => $otherUser->email,
            'first_name' => 'Άλλος',
            'last_name' => 'Προπονητής',
            'is_active' => true,
        ]);

        $ownClass = AcademyClass::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'coach_id' => $coach->id,
            'name' => 'U12 Own',
            'sport' => 'Μπάσκετ',
        ]);

        $otherClass = AcademyClass::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'coach_id' => $otherCoach->id,
            'name' => 'U14 Other',
            'sport' => 'Βόλεϊ',
        ]);

        $ownTraining = Training::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'academy_class_id' => $ownClass->id,
            'coach_id' => $coach->id,
            'title' => 'Own training',
            'starts_at' => now()->addDay()->setTime(18, 0),
            'ends_at' => now()->addDay()->setTime(19, 30),
            'status' => 'scheduled',
        ]);

        $otherTraining = Training::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'academy_class_id' => $otherClass->id,
            'coach_id' => $otherCoach->id,
            'title' => 'Other training',
            'starts_at' => now()->addDays(2)->setTime(18, 0),
            'ends_at' => now()->addDays(2)->setTime(19, 30),
            'status' => 'scheduled',
        ]);

        return compact(
            'club',
            'coachUser',
            'coach',
            'ownClass',
            'otherClass',
            'ownTraining',
            'otherTraining',
        );
    }

    public function test_coach_with_dashboard_permission_lands_on_coach_portal(): void
    {
        ['coachUser' => $coachUser] = $this->seedTwoCoachClasses();

        $this->assertSame('portal.coach', ClubHome::routeName($coachUser));

        $this->actingAs($coachUser)
            ->get('/')
            ->assertRedirect(route('portal.coach'));
    }

    public function test_athlete_lands_on_athlete_portal(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Athlete Home Club',
            'slug' => 'athlete-home-'.Str::random(6),
            'is_active' => true,
        ]);

        $athlete = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $athlete->clubs()->attach($club->id, [
            'role' => Roles::ATHLETE,
            'is_active' => true,
            'permissions' => json_encode(['dashboard']),
        ]);

        $this->assertSame('portal.athlete', ClubHome::routeName($athlete));

        $this->actingAs($athlete)
            ->get('/')
            ->assertRedirect(route('portal.athlete'));
    }

    public function test_coach_cannot_see_other_coach_trainings(): void
    {
        [
            'coachUser' => $coachUser,
            'ownTraining' => $ownTraining,
            'otherTraining' => $otherTraining,
        ] = $this->seedTwoCoachClasses();

        $this->actingAs($coachUser)
            ->get(route('admin.trainings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Trainings/Index')
                ->has('items.data', 1)
                ->where('items.data.0.id', $ownTraining->id)
            );

        $this->actingAs($coachUser)
            ->patch(route('admin.trainings.update', $otherTraining->id), [
                'title' => 'Hacked',
                'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
                'status' => 'cancelled',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('trainings', [
            'id' => $otherTraining->id,
            'title' => 'Other training',
            'status' => 'scheduled',
        ]);
    }
}
