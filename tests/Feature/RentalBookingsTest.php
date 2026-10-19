<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Facility;
use App\Models\RentalBooking;
use App\Models\RentalSetting;
use App\Models\Revenue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RentalBookingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{club: Club, user: User, facility: Facility}
     */
    private function seedClubWithFacility(): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Rental Club',
            'slug' => 'rental-'.Str::random(4),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $user->clubs()->attach($club->id, [
            'role' => 'admin',
            'permissions' => json_encode([]),
            'finance_own_entries_only' => false,
            'is_active' => true,
        ]);

        $facility = Facility::query()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'name' => 'ΚΕΝΤΡΙΚΗ ΣΑΛΑ',
            'is_rentable' => true,
        ]);

        RentalSetting::query()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'public_enabled' => true,
            'rules' => [
                'rules' => [[
                    'facilityId' => $facility->id,
                    'enabled' => true,
                    'slotMinutes' => 60,
                    'windows' => [[
                        'days' => [0, 1, 2, 3, 4, 5, 6],
                        'startTime' => '18:00',
                        'endTime' => '22:00',
                    ]],
                    'hourlyRate' => 80,
                    'hourlyRateFull' => 80,
                    'hourlyRateHalf' => 50,
                    'lockerRoomAvailable' => true,
                    'lockerRoomFee' => 10,
                ]],
                'notes' => '',
                'heroImageUrl' => null,
            ],
        ]);

        return compact('club', 'user', 'facility');
    }

    public function test_rentals_index_exposes_booking_workspace(): void
    {
        ['user' => $user] = $this->seedClubWithFacility();

        $this->actingAs($user)
            ->get(route('admin.rentals.index', ['tab' => 'bookings']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Rentals/Index')
                ->has('facilities', 1)
                ->has('occupancy')
                ->has('upcomingBookings')
                ->has('pastBookings')
                ->has('uncollected')
                ->where('uncollectedCount', 0)
            );
    }

    public function test_past_bookings_listed_separately_from_upcoming(): void
    {
        ['user' => $user, 'club' => $club, 'facility' => $facility] = $this->seedClubWithFacility();

        $past = RentalBooking::query()->create([
            'club_id' => $club->id,
            'facility_id' => $facility->id,
            'customer_name' => 'ΠΑΛΙΑ ΚΡΑΤΗΣΗ',
            'customer_phone' => '6900000001',
            'starts_at' => now()->subDays(5)->setTime(20, 0),
            'ends_at' => now()->subDays(5)->setTime(21, 0),
            'amount' => 50,
            'status' => 'confirmed',
            'meta' => [
                'paymentCollected' => true,
                'paymentMethod' => 'cash',
                'courtShare' => 'full',
                'source' => 'admin',
            ],
        ]);

        $upcoming = RentalBooking::query()->create([
            'club_id' => $club->id,
            'facility_id' => $facility->id,
            'customer_name' => 'ΝΕΑ ΚΡΑΤΗΣΗ',
            'customer_phone' => '6900000002',
            'starts_at' => now()->addDays(2)->setTime(20, 0),
            'ends_at' => now()->addDays(2)->setTime(21, 0),
            'amount' => 50,
            'status' => 'pending_payment',
            'meta' => [
                'paymentCollected' => false,
                'courtShare' => 'full',
                'source' => 'admin',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('admin.rentals.index', ['tab' => 'bookings']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Rentals/Index')
                ->where('upcomingBookings.0.id', $upcoming->id)
                ->where('pastBookings.0.id', $past->id)
            );
    }

    public function test_platform_admin_can_delete_booking(): void
    {
        ['club' => $club, 'facility' => $facility] = $this->seedClubWithFacility();

        $admin = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => true,
            'active_club_id' => $club->id,
        ]);
        $admin->clubs()->attach($club->id, [
            'role' => 'admin',
            'permissions' => json_encode([]),
            'finance_own_entries_only' => false,
            'is_active' => true,
        ]);

        $booking = RentalBooking::query()->create([
            'club_id' => $club->id,
            'facility_id' => $facility->id,
            'customer_name' => 'ΠΡΟΣ ΔΙΑΓΡΑΦΗ',
            'starts_at' => now()->subDay()->setTime(20, 0),
            'ends_at' => now()->subDay()->setTime(21, 0),
            'amount' => 50,
            'status' => 'confirmed',
            'meta' => ['paymentCollected' => true, 'paymentMethod' => 'cash'],
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.rentals.destroy', $booking->id))
            ->assertRedirect(route('admin.rentals.index', ['tab' => 'bookings']));

        $this->assertSoftDeleted('rental_bookings', ['id' => $booking->id]);
    }

    public function test_club_admin_cannot_delete_booking(): void
    {
        ['user' => $user, 'club' => $club, 'facility' => $facility] = $this->seedClubWithFacility();

        $booking = RentalBooking::query()->create([
            'club_id' => $club->id,
            'facility_id' => $facility->id,
            'customer_name' => 'ΧΩΡΙΣ ΔΙΑΓΡΑΦΗ',
            'starts_at' => now()->subDay()->setTime(20, 0),
            'ends_at' => now()->subDay()->setTime(21, 0),
            'amount' => 40,
            'status' => 'confirmed',
            'meta' => ['paymentCollected' => true],
        ]);

        $this->actingAs($user)
            ->delete(route('admin.rentals.destroy', $booking->id))
            ->assertForbidden();

        $this->assertDatabaseHas('rental_bookings', [
            'id' => $booking->id,
            'deleted_at' => null,
        ]);
    }

    public function test_can_book_and_collect_rental(): void
    {
        ['user' => $user, 'facility' => $facility] = $this->seedClubWithFacility();
        $date = now()->addDay()->toDateString();

        $this->actingAs($user)
            ->post(route('admin.rentals.book'), [
                'facility_id' => $facility->id,
                'date' => $date,
                'start_time' => '20:00',
                'end_time' => '21:00',
                'court_share' => 'half',
                'use_locker_room' => false,
                'customer_last_name' => 'ΝΙΚΟΣ',
                'customer_first_name' => 'ΤΕΣΤ',
                'customer_phone' => '6900000000',
                'customer_email' => null,
                'notes' => null,
                'special_discount' => 0,
                'paid_now' => false,
                'amount' => 50,
                'recurring' => false,
            ])
            ->assertRedirect(route('admin.rentals.index', ['tab' => 'bookings']));

        $booking = RentalBooking::query()->first();
        $this->assertNotNull($booking);
        $this->assertSame('pending', $booking->status);
        $this->assertEquals(50.0, (float) $booking->amount);
        $this->assertFalse((bool) data_get($booking->meta, 'paymentCollected'));

        $this->actingAs($user)
            ->post(route('admin.rentals.collect', $booking->id), [
                'payment_method' => 'cash',
            ])
            ->assertRedirect(route('admin.rentals.index', ['tab' => 'bookings']));

        $booking->refresh();
        $this->assertSame('confirmed', $booking->status);
        $this->assertTrue((bool) data_get($booking->meta, 'paymentCollected'));
        $this->assertSame('cash', data_get($booking->meta, 'paymentMethod'));

        $this->assertTrue(
            Revenue::query()
                ->where('club_id', $booking->club_id)
                ->where('legacy_id', 'rev_rent_'.$booking->id)
                ->exists()
        );
    }
}
