<?php

namespace App\Services\Payments;

use App\Models\PaymentSettlement;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class PaymentWebhookService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{settlement: PaymentSettlement, duplicate: bool}
     */
    public function settle(
        string $provider,
        string $clubId,
        string $idempotencyKey,
        ?string $externalId = null,
        ?string $amount = null,
        string $status = 'received',
        ?string $onlineCheckoutId = null,
        array $payload = [],
    ): array {
        $existing = PaymentSettlement::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return ['settlement' => $existing, 'duplicate' => true];
        }

        try {
            $settlement = DB::transaction(function () use (
                $provider,
                $clubId,
                $idempotencyKey,
                $externalId,
                $amount,
                $status,
                $onlineCheckoutId,
                $payload,
            ) {
                return PaymentSettlement::query()->create([
                    'club_id' => $clubId,
                    'online_checkout_id' => $onlineCheckoutId,
                    'provider' => $provider,
                    'external_id' => $externalId,
                    'idempotency_key' => $idempotencyKey,
                    'amount' => $amount,
                    'status' => $status,
                    'payload' => $payload,
                ]);
            });

            return ['settlement' => $settlement, 'duplicate' => false];
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                $settlement = PaymentSettlement::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->firstOrFail();

                return ['settlement' => $settlement, 'duplicate' => true];
            }

            throw $e;
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $code = (string) $e->getCode();

        return str_contains($code, '23000') || str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }
}
