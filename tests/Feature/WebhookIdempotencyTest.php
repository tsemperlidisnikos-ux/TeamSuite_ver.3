<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\OnlineCheckout;
use App\Models\PaymentSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_viva_webhook_is_idempotent(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Demo',
            'slug' => 'demo-wh',
            'is_active' => true,
        ]);

        OnlineCheckout::query()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'provider' => 'viva',
            'order_code' => 'ORD-100',
            'amount' => 50,
            'status' => 'pending',
        ]);

        $payload = ['EventId' => 'evt-1', 'OrderCode' => 'ORD-100'];

        $this->postJson('/webhooks/viva', $payload)->assertOk();
        $this->postJson('/webhooks/viva', $payload)->assertOk();

        $this->assertSame(1, PaymentSettlement::query()->count());
        $this->assertSame('paid', OnlineCheckout::query()->first()->status);
    }
}
