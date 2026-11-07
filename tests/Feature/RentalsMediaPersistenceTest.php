<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubSetting;
use App\Models\Facility;
use App\Models\RentalSetting;
use App\Models\User;
use App\Support\ClubPublicMedia;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RentalsMediaPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_availability_does_not_wipe_existing_facility_photo_when_client_sends_null(): void
    {
        Storage::fake('public');

        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Media Club',
            'slug' => 'media-'.Str::random(4),
            'is_active' => true,
        ]);
        ClubSetting::query()->create(['club_id' => $club->id]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
            'is_platform_admin' => false,
        ]);
        $user->clubs()->attach($club->id, [
            'role' => Roles::ADMIN,
            'permissions' => json_encode([]),
            'finance_own_entries_only' => false,
            'is_active' => true,
        ]);

        $storedPath = 'club-media/facilities/'.$club->id.'/court.jpg';
        Storage::disk('public')->put($storedPath, 'fake-image');

        $facility = Facility::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'name' => 'Κλειστό Α',
            'is_rentable' => true,
            'meta' => ['photoUrl' => $storedPath, 'sports' => ['Μπάσκετ']],
        ]);

        RentalSetting::query()->withoutGlobalScopes()->create([
            'club_id' => $club->id,
            'public_enabled' => true,
            'rules' => [
                'notes' => '',
                'heroImageUrl' => 'club-media/rent-hero/'.$club->id.'/hero.jpg',
                'rules' => [[
                    'facilityId' => $facility->id,
                    'enabled' => true,
                    'slotMinutes' => 60,
                    'hourlyRateFull' => 40,
                    'hourlyRateHalf' => 20,
                    'lockerRoomAvailable' => false,
                    'lockerRoomFee' => 0,
                    'windows' => [['days' => [1, 2, 3, 4, 5], 'startTime' => '18:00', 'endTime' => '22:00']],
                ]],
            ],
        ]);
        Storage::disk('public')->put('club-media/rent-hero/'.$club->id.'/hero.jpg', 'fake-hero');

        $this->actingAs($user)
            ->post(route('admin.rentals.save-availability'), [
                'public_enabled' => true,
                'notes' => 'updated',
                'hero_image_url' => null,
                'rules' => [[
                    'facilityId' => $facility->id,
                    'enabled' => true,
                    'slotMinutes' => 60,
                    'hourlyRateFull' => 45,
                    'hourlyRateHalf' => 20,
                    'lockerRoomAvailable' => false,
                    'lockerRoomFee' => 0,
                    'windows' => [['days' => [1, 2, 3, 4, 5], 'startTime' => '18:00', 'endTime' => '22:00']],
                ]],
                'facilities' => [[
                    'id' => $facility->id,
                    'photoUrl' => null,
                    'is_rentable' => true,
                    'sports' => ['Μπάσκετ'],
                ]],
            ])
            ->assertRedirect(route('admin.rentals.index'));

        $facility->refresh();
        $this->assertSame($storedPath, $facility->meta['photoUrl'] ?? null);

        $settings = RentalSetting::query()->withoutGlobalScopes()->where('club_id', $club->id)->first();
        $this->assertSame(
            'club-media/rent-hero/'.$club->id.'/hero.jpg',
            $settings?->rules['heroImageUrl'] ?? null,
        );
        $this->assertSame('updated', $settings?->rules['notes'] ?? null);
    }

    public function test_legacy_club_media_public_url_points_at_configured_base(): void
    {
        config(['services.teamsuite_legacy.media_base_url' => 'https://teamsuite-seven.vercel.app']);

        $url = ClubPublicMedia::publicUrl(
            '/api/club-media?p=ss360-media%2Fclub_1%2Fphoto.jpg&v=1'
        );

        $this->assertSame(
            'https://teamsuite-seven.vercel.app/api/club-media?p=ss360-media%2Fclub_1%2Fphoto.jpg&v=1',
            $url,
        );
    }

    public function test_public_rent_shows_enabled_facilities_even_when_is_rentable_flag_is_false(): void
    {
        Storage::fake('public');

        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Rent Flag Club',
            'slug' => 'rent-flag-'.Str::random(4),
            'is_active' => true,
        ]);

        $path = 'club-media/facilities/'.$club->id.'/court.jpg';
        Storage::disk('public')->put($path, 'fake-image');

        $facility = Facility::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'name' => 'Κλειστό Β',
            'is_rentable' => false,
            'meta' => ['photoUrl' => $path],
        ]);

        RentalSetting::query()->withoutGlobalScopes()->create([
            'club_id' => $club->id,
            'public_enabled' => true,
            'rules' => [[
                'facilityId' => $facility->id,
                'enabled' => true,
                'slotMinutes' => 60,
                'hourlyRateFull' => 40,
                'hourlyRateHalf' => 20,
                'windows' => [['days' => [1, 2, 3, 4, 5], 'startTime' => '18:00', 'endTime' => '22:00']],
            ]],
        ]);

        $this->get(route('public.rent.show', $club->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Rent')
                ->where('publicEnabled', true)
                ->has('facilities', 1)
                ->where('facilities.0.id', $facility->id)
                ->where('facilities.0.photoUrl', fn ($url) => is_string($url) && str_contains($url, '/storage/'.$path))
            );
    }
}
