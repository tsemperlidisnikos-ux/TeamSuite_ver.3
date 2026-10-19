<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AthleteUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_athlete(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Demo',
            'slug' => 'demo-upd',
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
            'first_name' => 'Νίκος',
            'last_name' => 'Παπαδόπουλος',
            'status' => 'active',
            'monthly_fee' => 40,
        ]);

        $response = $this->actingAs($user)->patch(route('admin.athletes.update', $athlete->id), [
            'first_name' => 'Γιάννης',
            'last_name' => 'Παπαδόπουλος',
            'email' => 'giannis@example.com',
            'phone' => '6901111111',
            'status' => 'trial',
            'monthly_fee' => 50,
            'sport' => 'Μπάσκετ',
        ]);

        $response->assertRedirect(route('admin.athletes.show', $athlete->id));

        $athlete->refresh();
        $this->assertSame('Γιάννης', $athlete->first_name);
        $this->assertSame('trial', $athlete->status);
        $this->assertEquals(50, (float) $athlete->monthly_fee);
        $this->assertSame('Μπάσκετ', $athlete->sport);
    }
}
