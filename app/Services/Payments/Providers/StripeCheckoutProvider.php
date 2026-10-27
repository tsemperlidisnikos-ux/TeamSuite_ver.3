<?php

namespace App\Services\Payments\Providers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeCheckoutProvider
{
    /**
     * @param  array<string, mixed>  $config
     * @return array{orderCode: string, checkoutUrl: string, method: string, fields: null, raw: array<string, mixed>}
     */
    public function createSession(
        array $config,
        int $amountCents,
        string $customerEmail,
        string $description,
        string $successUrl,
        string $cancelUrl,
    ): array {
        if ($amountCents < 50) {
            throw new RuntimeException('Το ποσό Stripe πρέπει να είναι τουλάχιστον 0,50 €.');
        }

        $secretKey = trim((string) ($config['secretKey'] ?? ''));
        if (! str_starts_with($secretKey, 'sk_')) {
            throw new RuntimeException('Μη έγκυρο Stripe Secret Key.');
        }

        $params = [
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items[0][quantity]' => '1',
            'line_items[0][price_data][currency]' => 'eur',
            'line_items[0][price_data][unit_amount]' => (string) $amountCents,
            'line_items[0][price_data][product_data][name]' => mb_substr($description, 0, 120),
        ];
        if (str_contains($customerEmail, '@')) {
            $params['customer_email'] = $customerEmail;
        }

        $created = Http::connectTimeout(5)
            ->timeout(20)
            ->withToken($secretKey)
            ->asForm()
            ->post('https://api.stripe.com/v1/checkout/sessions', $params);

        $url = $created->json('url');
        $id = $created->json('id');
        if (! $created->successful() || ! is_string($url) || ! is_string($id)) {
            $msg = $created->json('error.message') ?? ('Αποτυχία Stripe (HTTP '.$created->status().')');
            throw new RuntimeException((string) $msg);
        }

        return [
            'orderCode' => $id,
            'checkoutUrl' => $url,
            'method' => 'GET',
            'fields' => null,
            'raw' => $created->json() ?? [],
        ];
    }
}
