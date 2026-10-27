<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\AthleteTransaction;
use App\Models\Club;
use App\Models\ClubSetting;
use App\Models\OnlineCheckout;
use App\Models\ParentAthleteLink;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class OnlineCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_can_start_viva_checkout(): void
    {
        Http::fake([
            '*/connect/token' => Http::response(['access_token' => 'tok'], 200),
            '*/checkout/v2/orders' => Http::response(['orderCode' => '999001'], 200),
        ]);

        [$parent, $athlete] = $this->seedParentWithAthleteBalance();

        $this->actingAs($parent)
            ->postJson(route('portal.parent.checkout'), [
                'athlete_id' => $athlete->id,
                'provider' => 'viva',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('provider', 'viva')
            ->assertJsonPath('orderCode', '999001');

        $this->assertDatabaseHas('online_checkouts', [
            'athlete_id' => $athlete->id,
            'provider' => 'viva',
            'order_code' => '999001',
            'status' => 'pending',
        ]);
    }

    public function test_webhook_creates_athlete_payment_once(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Demo',
            'slug' => 'demo-pay',
            'is_active' => true,
        ]);
        $athlete = Athlete::query()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Νίκος',
            'last_name' => 'Παπαδόπουλος',
            'status' => 'active',
        ]);
        OnlineCheckout::query()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'athlete_id' => $athlete->id,
            'provider' => 'viva',
            'order_code' => 'ORD-200',
            'amount' => 25.5,
            'status' => 'pending',
        ]);

        $payload = ['EventId' => 'evt-pay-1', 'OrderCode' => 'ORD-200'];
        $this->postJson('/webhooks/viva', $payload)->assertOk();
        $this->postJson('/webhooks/viva', $payload)->assertOk();

        $this->assertSame(1, AthleteTransaction::query()->where('type', 'payment')->count());
        $this->assertSame('paid', OnlineCheckout::query()->first()->status);
    }

    /**
     * @return array{0: User, 1: Athlete}
     */
    private function seedParentWithAthleteBalance(): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Demo Club',
            'slug' => 'demo-checkout',
            'is_active' => true,
        ]);

        $parent = User::factory()->create([
            'email' => 'parent-checkout@example.com',
            'active_club_id' => $club->id,
            'email_verified_at' => now(),
        ]);
        $parent->clubs()->attach($club->id, [
            'role' => Roles::PARENT,
            'is_active' => true,
            'finance_own_entries_only' => false,
        ]);

        ClubSetting::query()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'viva' => [
                'enabled' => true,
                'clientId' => 'cid',
                'clientSecret' => 'csecret',
                'sourceCode' => '1234',
                'demo' => true,
            ],
        ]);

        $athlete = Athlete::query()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Μαρία',
            'last_name' => 'Ιωάννου',
            'status' => 'active',
        ]);

        ParentAthleteLink::query()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'parent_user_id' => $parent->id,
            'athlete_id' => $athlete->id,
            'receives_email' => true,
            'receives_sms' => true,
            'receives_push' => true,
        ]);

        AthleteTransaction::query()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'athlete_id' => $athlete->id,
            'type' => 'charge',
            'amount' => 40,
            'comments' => 'Συνδρομή',
        ]);

        return [$parent, $athlete];
    }
}
