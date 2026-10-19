<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\OnlineCheckout;
use App\Services\Payments\PaymentWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Unified payment webhooks: resolve checkout by order code, settle idempotently.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentWebhookService $webhooks,
    ) {}

    public function viva(Request $request): Response
    {
        return $this->ingest('viva', $request);
    }

    public function stripe(Request $request): Response
    {
        return $this->ingest('stripe', $request);
    }

    public function eurobank(Request $request): Response
    {
        return $this->ingest('eurobank', $request);
    }

    private function ingest(string $provider, Request $request): Response
    {
        $payload = $request->all();
        $externalId = (string) (
            $payload['EventId']
            ?? $payload['id']
            ?? $payload['orderCode']
            ?? $payload['OrderCode']
            ?? ''
        );
        $orderCode = (string) (
            $payload['OrderCode']
            ?? $payload['orderCode']
            ?? $payload['data']['object']['id']
            ?? ''
        );

        $checkout = $orderCode !== ''
            ? OnlineCheckout::query()->withoutGlobalScopes()->where('order_code', $orderCode)->first()
            : null;

        if (! $checkout) {
            return response('ok-unmatched', 200);
        }

        $idempotencyKey = $provider.':'.($externalId !== '' ? $externalId : $orderCode);

        $result = $this->webhooks->settle(
            provider: $provider,
            clubId: $checkout->club_id,
            idempotencyKey: $idempotencyKey,
            externalId: $externalId !== '' ? $externalId : null,
            amount: $checkout->amount !== null ? (string) $checkout->amount : null,
            status: 'received',
            onlineCheckoutId: $checkout->id,
            payload: $payload,
        );

        if (! $result['duplicate']) {
            $checkout->update(['status' => 'paid']);
        }

        return response($result['duplicate'] ? 'ok-duplicate' : 'ok', 200);
    }
}
