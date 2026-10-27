<?php

namespace App\Services\Payments\Providers;

use Illuminate\Support\Str;
use RuntimeException;

class EurobankCheckoutProvider
{
    /**
     * @param  array<string, mixed>  $config
     * @return array{orderCode: string, checkoutUrl: string, method: string, fields: array<string, string>, raw: array<string, mixed>}
     */
    public function createPayment(
        array $config,
        int $amountCents,
        string $payerEmail,
        string $orderDesc,
        string $confirmUrl,
        string $cancelUrl,
    ): array {
        if ($amountCents < 30) {
            throw new RuntimeException('Το ποσό Eurobank πρέπει να είναι τουλάχιστον 0,30 €.');
        }

        $merchantId = trim((string) ($config['merchantId'] ?? ''));
        $secretKey = trim((string) ($config['secretKey'] ?? $config['secret'] ?? ''));
        if ($merchantId === '' || $secretKey === '') {
            throw new RuntimeException('Ελλιπή Eurobank credentials.');
        }

        $env = (($config['environment'] ?? null) === 'live' || ($config['demo'] ?? true) === false)
            ? 'live'
            : 'demo';
        $checkoutUrl = $env === 'live'
            ? 'https://vpos.eurocommerce.gr/vpos/shophandlermpi'
            : 'https://eurocommerce-test.cardlink.gr/vpos/shophandlermpi';

        $orderid = ('TS'.now()->getTimestampMs().Str::lower(Str::random(6)));
        $orderid = substr($orderid, 0, 20);

        $confirmWithOrder = str_contains($confirmUrl, 'ORDERID')
            ? str_replace('ORDERID', $orderid, $confirmUrl)
            : $confirmUrl.(str_contains($confirmUrl, '?') ? '&' : '?').'s='.urlencode($orderid);

        $fields = [
            'version' => '2',
            'mid' => $merchantId,
            'orderid' => $orderid,
            'orderDesc' => mb_substr($orderDesc, 0, 128),
            'orderAmount' => number_format($amountCents / 100, 2, '.', ''),
            'currency' => '978',
            'billCountry' => 'GR',
            'confirmUrl' => $confirmWithOrder,
            'cancelUrl' => $cancelUrl,
        ];
        if (str_contains($payerEmail, '@')) {
            $fields['payerEmail'] = $payerEmail;
        }
        $fields['digest'] = $this->digestV2($fields, $secretKey);

        return [
            'orderCode' => $orderid,
            'checkoutUrl' => $checkoutUrl,
            'method' => 'POST',
            'fields' => $fields,
            'raw' => ['orderid' => $orderid],
        ];
    }

    /** @param  array<string, string>  $fields */
    private function digestV2(array $fields, string $secret): string
    {
        $keys = array_keys(array_filter(
            $fields,
            fn ($value, $key) => $key !== 'digest' && $value !== '',
            ARRAY_FILTER_USE_BOTH,
        ));
        sort($keys, SORT_STRING);
        $concatenated = '';
        foreach ($keys as $key) {
            $concatenated .= $fields[$key];
        }

        return base64_encode(hash('sha256', $concatenated.$secret, true));
    }
}
