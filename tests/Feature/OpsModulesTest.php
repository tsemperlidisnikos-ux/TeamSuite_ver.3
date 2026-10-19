<?php

namespace Tests\Feature;

use App\Models\AcademyClass;
use App\Models\Athlete;
use App\Models\AthleteTransaction;
use App\Models\Attendance;
use App\Models\Club;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OpsModulesTest extends TestCase
{
    use RefreshDatabase;

    private function seedClub(): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Ops Club',
            'slug' => 'ops-'.Str::random(6),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $user->clubs()->attach($club->id, ['role' => 'admin', 'is_active' => true]);

        $class = AcademyClass::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'name' => 'U12 Μπάσκετ',
            'sport' => 'Μπάσκετ',
            'monthly_fee' => 40,
        ]);

        $athlete = Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Άννα',
            'last_name' => 'Νικολάου',
            'status' => 'active',
            'monthly_fee' => 40,
            'gdpr_consent' => 'pending',
        ]);
        $class->athletes()->attach($athlete->id, ['is_primary' => true]);

        return compact('club', 'user', 'class', 'athlete');
    }

    public function test_attendance_grid_and_upsert(): void
    {
        ['user' => $user, 'class' => $class, 'athlete' => $athlete] = $this->seedClub();
        $date = now()->toDateString();

        $this->actingAs($user)
            ->get(route('admin.attendance.index', ['class_id' => $class->id, 'date' => $date]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Attendance/Index')
                ->has('athletes', 1)
            );

        $this->actingAs($user)
            ->post(route('admin.attendance.upsert'), [
                'class_id' => $class->id,
                'date' => $date,
                'athlete_id' => $athlete->id,
                'status' => 'present',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'athlete_id' => $athlete->id,
            'status' => 'present',
        ]);
    }

    public function test_transactions_per_athlete(): void
    {
        ['user' => $user, 'athlete' => $athlete] = $this->seedClub();

        $month = (int) now()->month;
        $year = (int) now()->year;
        $seasonStart = $month >= 8 ? $year : $year - 1;

        $this->actingAs($user)
            ->from(route('admin.transactions.index'))
            ->post(route('admin.transactions.store'), [
                'athlete_id' => $athlete->id,
                'type' => 'charge',
                'amount' => 40,
                'month' => $month,
                'year' => $year,
            ])
            ->assertRedirect()
            ->assertRedirectContains('athlete_id='.$athlete->id)
            ->assertRedirectContains('season_start='.$seasonStart);

        $this->actingAs($user)
            ->post(route('admin.transactions.store'), [
                'athlete_id' => $athlete->id,
                'type' => 'payment',
                'amount' => 20,
                'month' => $month,
                'year' => $year,
                'payment_method' => 'cash',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('admin.transactions.index', [
                'athlete_id' => $athlete->id,
                'season_start' => $seasonStart,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Transactions/Index')
                ->where('balance.owed', 20)
                ->has('transactions', 2)
                ->has('monthRows', 12)
                ->has('athletes')
            );

        $this->assertSame(2, AthleteTransaction::query()->withoutGlobalScopes()->where('athlete_id', $athlete->id)->count());
    }

    public function test_class_profile_attach_detach(): void
    {
        ['user' => $user, 'club' => $club, 'class' => $class] = $this->seedClub();

        $other = Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Πέτρος',
            'last_name' => 'Γεωργίου',
            'status' => 'active',
            'gdpr_consent' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('admin.classes.show', $class->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Classes/Show')
                ->where('classItem.name', 'U12 Μπάσκετ')
            );

        $this->actingAs($user)
            ->post(route('admin.classes.athletes.attach', $class->id), [
                'athlete_id' => $other->id,
            ])
            ->assertRedirect();

        $this->assertTrue($class->athletes()->where('athletes.id', $other->id)->exists());

        $this->actingAs($user)
            ->delete(route('admin.classes.athletes.detach', [$class->id, $other->id]))
            ->assertRedirect();

        $this->assertFalse($class->athletes()->where('athletes.id', $other->id)->exists());
    }

    public function test_athletes_csv_import_export(): void
    {
        ['user' => $user, 'club' => $club] = $this->seedClub();

        $csv = "first_name;last_name;email;status;sport;monthly_fee\nΓιάννης;Παπαδάκης;giannis@example.com;active;Βόλεϊ;35\n";
        $file = UploadedFile::fake()->createWithContent('athletes.csv', $csv);

        $this->actingAs($user)
            ->post(route('admin.athletes.import'), ['file' => $file])
            ->assertRedirect(route('admin.athletes.index'));

        $this->assertDatabaseHas('athletes', [
            'club_id' => $club->id,
            'email' => 'giannis@example.com',
            'last_name' => 'Παπαδάκης',
        ]);

        $this->actingAs($user)
            ->get(route('admin.athletes.export'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_calendar_month_view(): void
    {
        ['user' => $user, 'club' => $club] = $this->seedClub();

        Training::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'title' => 'Προπόνηση Α',
            'starts_at' => now()->startOfMonth()->setTime(18, 0),
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
                ->where('stats.trainings', 1)
                ->has('events')
            );
    }
}
