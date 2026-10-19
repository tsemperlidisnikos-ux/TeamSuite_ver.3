<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\Club;
use App\Models\ProgressReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AthleteProfileTest extends TestCase
{
    use RefreshDatabase;

    private function seedAdminClub(): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Demo Club',
            'slug' => 'demo-profile-'.Str::random(6),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $user->clubs()->attach($club->id, ['role' => 'admin', 'is_active' => true]);

        $athlete = Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Μαρία',
            'last_name' => 'Ιωάννου',
            'status' => 'active',
            'monthly_fee' => 35,
            'sport' => 'Βόλεϊ',
            'gdpr_consent' => 'pending',
            'meta' => [
                'blood_type' => 'A+',
                'city' => 'Αθήνα',
            ],
        ]);

        return compact('club', 'user', 'athlete');
    }

    public function test_admin_can_view_athlete_profile(): void
    {
        ['user' => $user, 'athlete' => $athlete] = $this->seedAdminClub();

        $this->actingAs($user)
            ->get(route('admin.athletes.show', $athlete->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Athletes/Show')
                ->where('athlete.id', $athlete->id)
                ->where('athlete.last_name', 'Ιωάννου')
                ->where('athlete.blood_type', 'A+')
                ->has('classes')
                ->has('transactions')
                ->has('attendances')
                ->has('progressReports')
                ->has('changeLogs')
                ->has('announcements')
                ->has('balance')
            );
    }

    public function test_admin_can_filter_athletes_list(): void
    {
        ['user' => $user, 'athlete' => $athlete] = $this->seedAdminClub();

        Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $athlete->club_id,
            'first_name' => 'Κώστας',
            'last_name' => 'Δοκιμής',
            'status' => 'trial',
            'monthly_fee' => 0,
            'gdpr_consent' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('admin.athletes.index', ['status' => 'active', 'q' => 'Ιωάννου']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Athletes/Index')
                ->has('rows.data', 1)
                ->where('filters.status', 'active')
            );
    }

    public function test_admin_can_update_profile_and_add_progress(): void
    {
        ['user' => $user, 'athlete' => $athlete] = $this->seedAdminClub();

        $this->actingAs($user)
            ->patch(route('admin.athletes.update', $athlete->id), [
                'first_name' => 'Μαρία',
                'last_name' => 'Ιωάννου',
                'status' => 'active',
                'monthly_fee' => 40,
                'sport' => 'Βόλεϊ',
                'city' => 'Θεσσαλονίκη',
                'blood_type' => 'O+',
                'health_card' => true,
                'gdpr_consent' => 'full',
            ])
            ->assertRedirect(route('admin.athletes.show', $athlete->id));

        $athlete->refresh();
        $this->assertSame('Θεσσαλονίκη', $athlete->metaGet('city'));
        $this->assertSame('O+', $athlete->metaGet('blood_type'));
        $this->assertTrue((bool) $athlete->health_card);

        $this->actingAs($user)
            ->post(route('admin.athletes.progress.store', $athlete->id), [
                'report_date' => now()->toDateString(),
                'title' => 'Πρόοδος',
                'rating' => 4,
                'body' => 'Καλή παρουσία στην προπόνηση.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('progress_reports', [
            'athlete_id' => $athlete->id,
            'body' => 'Καλή παρουσία στην προπόνηση.',
        ]);
        $this->assertSame(1, ProgressReport::query()->withoutGlobalScopes()->where('athlete_id', $athlete->id)->count());
    }

    public function test_coaches_list_supports_status_filter(): void
    {
        ['user' => $user, 'club' => $club] = $this->seedAdminClub();

        \App\Models\Coach::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Άκης',
            'last_name' => 'Ενεργός',
            'is_active' => true,
        ]);
        \App\Models\Coach::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Νίκος',
            'last_name' => 'Ανενεργός',
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->get(route('admin.coaches.index', ['status' => '1']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Coaches/Index')
                ->has('items.data', 1)
                ->where('filters.status', '1')
            );
    }
}
