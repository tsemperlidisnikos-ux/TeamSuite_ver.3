<?php

namespace App\Services\Payments\Providers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class VivaCheckoutProvider
{
    /**
     * @param  array<string, mixed>  $config
     * @return array{orderCode: string, checkoutUrl: string, method: string, fields: null, raw: array<string, mixed>}
     */
    public function createOrder(
        array $config,
        int $amountCents,
        string $customerEmail,
        string $customerFullName,
        string $merchantTrns,
    ): array {
        if ($amountCents < 30) {
            throw new RuntimeException('Το ποσό Viva πρέπει να είναι τουλάχιστον 0,30 €.');
        }

        $clientId = trim((string) ($config['clientId'] ?? ''));
        $clientSecret = trim((string) ($config['clientSecret'] ?? ''));
        $sourceCode = trim((string) ($config['sourceCode'] ?? ''));
        $env = $this->environment($config);

        if ($clientId === '' || $clientSecret === '' || $sourceCode === '') {
            throw new RuntimeException('Ελλιπή Viva credentials.');
        }

        $hosts = $env === 'live'
            ? [
                'accounts' => 'https://accounts.vivapayments.com',
                'api' => 'https://api.vivapayments.com',
                'checkout' => 'https://www.vivapayments.com/web/checkout',
            ]
            : [
                'accounts' => 'https://demo-accounts.vivapayments.com',
                'api' => 'https://demo-api.vivapayments.com',
                'checkout' => 'https://demo.vivapayments.com/web/checkout',
            ];

        $tokenRes = Http::connectTimeout(5)
            ->timeout(20)
            ->withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post($hosts['accounts'].'/connect/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (! $tokenRes->successful() || ! is_string($tokenRes->json('access_token'))) {
            throw new RuntimeException(
                (string) ($tokenRes->json('error_description')
                    ?? $tokenRes->json('error')
                    ?? 'Αποτυχία αυθεντικοποίησης Viva')
            );
        }

        $customer = array_filter([
            'email' => $customerEmail !== '' ? $customerEmail : null,
            'fullName' => $customerFullName !== '' ? $customerFullName : null,
        ]);

        $orderRes = Http::connectTimeout(5)
            ->timeout(20)
            ->withToken($tokenRes->json('access_token'))
            ->acceptJson()
            ->post($hosts['api'].'/checkout/v2/orders', [
                'amount' => $amountCents,
                'customerTrns' => $merchantTrns,
                'merchantTrns' => $merchantTrns,
                'sourceCode' => $sourceCode,
                'customer' => $customer === [] ? new \stdClass : $customer,
            ]);

        $orderCode = $orderRes->json('orderCode');
        if (! $orderRes->successful() || $orderCode === null) {
            throw new RuntimeException(
                (string) ($orderRes->json('ErrorText')
                    ?? $orderRes->json('message')
                    ?? 'Αποτυχία δημιουργίας Viva order')
            );
        }

        return [
            'orderCode' => (string) $orderCode,
            'checkoutUrl' => $hosts['checkout'].'?ref='.urlencode((string) $orderCode),
            'method' => 'GET',
            'fields' => null,
            'raw' => $orderRes->json() ?? [],
        ];
    }

    /** @param  array<string, mixed>  $config */
    private function environment(array $config): string
    {
        if (($config['environment'] ?? null) === 'live' || ($config['demo'] ?? true) === false) {
            return 'live';
        }

        return 'demo';
    }
}
