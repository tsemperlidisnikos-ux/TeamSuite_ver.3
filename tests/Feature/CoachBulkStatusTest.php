<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Coach;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoachBulkStatusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{club: Club, user: User}
     */
    private function seedClub(): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Bulk Club',
            'slug' => 'bulk-'.Str::random(5),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $user->clubs()->attach($club->id, ['role' => 'admin', 'is_active' => true]);

        return compact('club', 'user');
    }

    public function test_coaches_bulk_status_update(): void
    {
        ['club' => $club, 'user' => $user] = $this->seedClub();

        $active = Coach::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Γιώργος',
            'last_name' => 'Ενεργός',
            'is_active' => true,
        ]);
        $also = Coach::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Νίκος',
            'last_name' => 'Δεύτερος',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('admin.coaches.bulk'), [
                'ids' => [$active->id, $also->id],
                'is_active' => '0',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse((bool) $active->fresh()->is_active);
        $this->assertFalse((bool) $also->fresh()->is_active);
    }

    public function test_staff_bulk_status_update(): void
    {
        ['club' => $club, 'user' => $user] = $this->seedClub();

        $member = StaffMember::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Μαρία',
            'last_name' => 'Γραμματεία',
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->post(route('admin.staff.bulk'), [
                'ids' => [$member->id],
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue((bool) $member->fresh()->is_active);
    }
}
