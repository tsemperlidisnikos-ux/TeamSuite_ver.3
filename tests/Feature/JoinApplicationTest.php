<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubSetting;
use App\Models\RegistrationApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class JoinApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_join_creates_pending_application(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Join Club',
            'slug' => 'join-club',
            'is_active' => true,
        ]);

        ClubSetting::query()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'public_join_enabled' => true,
        ]);

        $response = $this->post('/join/join-club', [
            'type' => 'full',
            'first_name' => 'Γιάννης',
            'last_name' => 'Παπαδόπουλος',
            'email' => 'parent@example.com',
            'phone' => '6900000000',
            'gdpr_accepted' => '1',
        ]);

        $response->assertRedirect();
        $this->assertSame(1, RegistrationApplication::query()->withoutGlobalScopes()->count());
        $this->assertSame('pending', RegistrationApplication::query()->withoutGlobalScopes()->first()->status);
    }
}
