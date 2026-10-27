<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\AthleteTransaction;
use App\Models\OnlineCheckout;
use App\Services\Payments\PaymentWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Unified payment webhooks: resolve checkout by order code, settle idempotently,
 * and post an athlete ledger payment on first successful settle.
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
        $eventData = is_array($payload['EventData'] ?? null) ? $payload['EventData'] : [];

        $externalId = (string) (
            $payload['EventId']
            ?? $payload['id']
            ?? $eventData['TransactionId']
            ?? $payload['orderCode']
            ?? $payload['OrderCode']
            ?? $payload['orderid']
            ?? ''
        );

        $orderCode = (string) (
            $payload['OrderCode']
            ?? $payload['orderCode']
            ?? $eventData['OrderCode']
            ?? $payload['data']['object']['id']
            ?? $payload['orderid']
            ?? $payload['s']
            ?? ''
        );

        $checkout = $orderCode !== ''
            ? OnlineCheckout::query()->withoutGlobalScopes()->where('order_code', $orderCode)->first()
            : null;

        if (! $checkout) {
            return response('ok-unmatched', 200);
        }

        $idempotencyKey = $provider.':'.($externalId !== '' ? $externalId : $orderCode);

        $result = DB::transaction(function () use ($provider, $checkout, $idempotencyKey, $externalId, $payload) {
            $settled = $this->webhooks->settle(
                provider: $provider,
                clubId: $checkout->club_id,
                idempotencyKey: $idempotencyKey,
                externalId: $externalId !== '' ? $externalId : null,
                amount: $checkout->amount !== null ? (string) $checkout->amount : null,
                status: 'received',
                onlineCheckoutId: $checkout->id,
                payload: $payload,
            );

            if (! $settled['duplicate'] && $checkout->status !== 'paid') {
                $checkout->update(['status' => 'paid']);
                $this->postLedgerPayment($checkout, $provider);
            }

            return $settled;
        });

        return response($result['duplicate'] ? 'ok-duplicate' : 'ok', 200);
    }

    private function postLedgerPayment(OnlineCheckout $checkout, string $provider): void
    {
        if (! $checkout->athlete_id) {
            return;
        }

        $exists = AthleteTransaction::query()->withoutGlobalScopes()
            ->where('club_id', $checkout->club_id)
            ->where('meta->online_checkout_id', $checkout->id)
            ->exists();

        if ($exists) {
            return;
        }

        AthleteTransaction::query()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $checkout->club_id,
            'athlete_id' => $checkout->athlete_id,
            'type' => 'payment',
            'amount' => $checkout->amount,
            'payment_method' => 'online_'.$provider,
            'comments' => 'Online πληρωμή '.$provider.' · '.$checkout->order_code,
            'meta' => [
                'online_checkout_id' => $checkout->id,
                'provider' => $provider,
                'order_code' => $checkout->order_code,
            ],
        ]);
    }
}
