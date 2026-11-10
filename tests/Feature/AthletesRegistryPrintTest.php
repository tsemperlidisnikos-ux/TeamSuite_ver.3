<?php

namespace Tests\Feature;

use App\Models\Association;
use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\ClothingPackage;
use App\Models\Club;
use App\Models\ClubSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AthletesRegistryPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_prints_include_athlete_registry_filter_fields(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Registry Club',
            'slug' => 'registry-'.Str::random(4),
            'is_active' => true,
        ]);

        ClubSetting::query()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'profile' => ['custom_charge_label' => 'Στολή Extra'],
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $user->clubs()->attach($club->id, [
            'role' => 'admin',
            'permissions' => json_encode(['prints']),
            'finance_own_entries_only' => false,
            'is_active' => true,
        ]);

        Association::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'name' => 'Α.Σ. ΑΠΟΛΛΩΝ',
        ]);

        $package = ClothingPackage::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'name' => 'BASIC',
            'price' => 0,
        ]);

        $athlete = Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Νίκος',
            'last_name' => 'Παπαδόπουλος',
            'status' => 'active',
            'gender' => 'boy',
            'birth_date' => '2012-05-01',
            'enrolled_at' => '2024-09-01',
            'health_card' => true,
            'meta' => [
                'registration_charge' => true,
                'monthly_charge' => true,
                'season_ticket' => true,
                'custom_charge' => true,
                'photo_url' => 'https://example.com/photo.jpg',
                'club_name' => 'Α.Σ. ΑΠΟΛΛΩΝ',
                'clothing_package_ids' => [$package->id],
                'registration_number' => 'Δ-100',
                'uniform_received' => true,
                'uniform_size' => 'Medium',
            ],
        ]);

        Attendance::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'athlete_id' => $athlete->id,
            'status' => 'present',
            'attended_on' => '2024-10-15',
        ]);

        $this->actingAs($user)
            ->get(route('admin.prints.index', [
                'type' => 'athletes-registry',
                'view' => 'results',
                'date_from' => '2024-10-01',
                'date_to' => '2024-10-31',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Prints/Index')
                ->where('customChargeLabel', 'Στολή Extra')
                ->has('associations', 1)
                ->has('clothingPackages', 1)
                ->has('athletes', 1)
                ->where('athletes.0.has_photo', true)
                ->where('athletes.0.health_card', true)
                ->where('athletes.0.season_ticket', true)
                ->where('athletes.0.custom_charge', true)
                ->where('athletes.0.club_name', 'Α.Σ. ΑΠΟΛΛΩΝ')
                ->where('athletes.0.registration_number', 'Δ-100')
                ->where('athletes.0.uniform_received', true)
                ->where('athletes.0.uniform_size', 'Medium')
                ->where('athletes.0.has_attendance', true)
                ->where('athletes.0.clothing_package_ids.0', $package->id)
            );
    }
}
