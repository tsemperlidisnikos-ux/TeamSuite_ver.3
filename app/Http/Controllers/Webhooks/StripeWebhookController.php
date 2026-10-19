<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentWebhookService $webhooks): JsonResponse
    {
        $payload = $request->all();
        $clubId = (string) ($request->input('club_id') ?? data_get($payload, 'data.object.metadata.club_id'));
        $idempotencyKey = (string) ($request->input('idempotency_key') ?? $request->header('Stripe-Request-Id') ?? data_get($payload, 'id'));
        $externalId = data_get($payload, 'data.object.id') ?? $request->input('external_id');
        $amount = data_get($payload, 'data.object.amount');
        if ($amount !== null && is_numeric($amount)) {
            $amount = bcdiv((string) $amount, '100', 2);
        }

        $result = $webhooks->settle(
            provider: 'stripe',
            clubId: $clubId,
            idempotencyKey: $idempotencyKey,
            externalId: $externalId ? (string) $externalId : null,
            amount: $amount !== null ? (string) $amount : null,
            status: (string) ($request->input('status') ?? data_get($payload, 'type') ?? 'received'),
            payload: $payload,
        );

        return response()->json([
            'ok' => true,
            'duplicate' => $result['duplicate'],
            'settlement_id' => $result['settlement']->id,
        ], $result['duplicate'] ? 200 : 201);
    }
}
