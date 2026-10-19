<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubActivityLog;
use App\Models\Facility;
use App\Models\RentalBooking;
use App\Models\User;
use App\Services\Audit\ClubActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClubActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_view_club_activity_log(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Activity Club',
            'slug' => 'activity-'.Str::random(4),
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => true,
            'active_club_id' => $club->id,
        ]);

        ClubActivityLog::query()->create([
            'club_id' => $club->id,
            'user_id' => $admin->id,
            'action' => 'rental.book',
            'summary' => 'Δημιούργησε κράτηση ενοικίασης',
            'description' => 'ΚΕΝΤΡΙΚΗ ΣΑΛΑ · 19/10/2026 20:00–21:00 · ΝΙΚΟΣ · 50,00 €',
            'occurred_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('platform.activity.index', ['club_id' => $club->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/ClubActivityLog')
                ->has('groups', 1)
                ->where('groups.0.items.0.summary', 'Δημιούργησε κράτηση ενοικίασης')
                ->where('groups.0.items.0.user_name', $admin->name)
            );
    }

    public function test_non_platform_admin_cannot_view_activity_log(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Club',
            'slug' => 'club-'.Str::random(4),
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_platform_admin' => false,
            'active_club_id' => $club->id,
        ]);
        $user->clubs()->attach($club->id, ['role' => 'admin', 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('platform.activity.index'))
            ->assertForbidden();
    }

    public function test_rental_delete_writes_activity_log(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Rental Club',
            'slug' => 'rental-'.Str::random(4),
            'is_active' => true,
        ]);
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

        $facility = Facility::query()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'name' => 'ΚΕΝΤΡΙΚΗ ΣΑΛΑ',
            'is_rentable' => true,
        ]);

        $booking = RentalBooking::query()->create([
            'club_id' => $club->id,
            'facility_id' => $facility->id,
            'customer_name' => 'ΝΙΚΟΣ',
            'starts_at' => now()->subDay()->setTime(20, 0),
            'ends_at' => now()->subDay()->setTime(21, 0),
            'amount' => 50,
            'status' => 'confirmed',
            'meta' => ['facilityName' => 'ΚΕΝΤΡΙΚΗ ΣΑΛΑ', 'paymentCollected' => true],
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.rentals.destroy', $booking->id))
            ->assertRedirect();

        $this->assertDatabaseHas('club_activity_logs', [
            'club_id' => $club->id,
            'user_id' => $admin->id,
            'action' => 'rental.delete',
            'summary' => 'Διέγραψε κράτηση ενοικίασης',
        ]);
    }

    public function test_logger_records_description_and_ip(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Log Club',
            'slug' => 'log-'.Str::random(4),
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'is_platform_admin' => true,
            'active_club_id' => $club->id,
        ]);

        $this->actingAs($user);
        app(ClubActivityLogger::class)->record(
            $club->id,
            'athlete.update',
            'Ενημέρωσε στοιχείο αθλητή',
            'Παπαδόπουλος Νίκος · phone: «6901» → «6902»',
            'athlete',
            (string) Str::uuid(),
        );

        $row = ClubActivityLog::query()->first();
        $this->assertNotNull($row);
        $this->assertSame('Παπαδόπουλος Νίκος · phone: «6901» → «6902»', $row->description);
        $this->assertNotNull($row->occurred_at);
    }
}
