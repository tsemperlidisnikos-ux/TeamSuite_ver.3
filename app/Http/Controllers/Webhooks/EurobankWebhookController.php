<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EurobankWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentWebhookService $webhooks): JsonResponse
    {
        $clubId = (string) ($request->input('club_id') ?? $request->input('merchantReference'));
        $idempotencyKey = (string) ($request->input('idempotency_key') ?? $request->input('transactionId') ?? $request->input('orderId'));
        $externalId = $request->input('transactionId') ?? $request->input('external_id');
        $amount = $request->input('amount');

        $result = $webhooks->settle(
            provider: 'eurobank',
            clubId: $clubId,
            idempotencyKey: $idempotencyKey,
            externalId: $externalId ? (string) $externalId : null,
            amount: $amount !== null ? (string) $amount : null,
            status: (string) ($request->input('status') ?? 'received'),
            payload: $request->all(),
        );

        return response()->json([
            'ok' => true,
            'duplicate' => $result['duplicate'],
            'settlement_id' => $result['settlement']->id,
        ], $result['duplicate'] ? 200 : 201);
    }
}
