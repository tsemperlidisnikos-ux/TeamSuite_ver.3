<?php

namespace App\Services\Payments;

use App\Models\Athlete;
use App\Models\Club;
use App\Models\ClubSetting;
use App\Models\OnlineCheckout;
use App\Services\Payments\Providers\EurobankCheckoutProvider;
use App\Services\Payments\Providers\StripeCheckoutProvider;
use App\Services\Payments\Providers\VivaCheckoutProvider;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class OnlineCheckoutService
{
    public function __construct(
        private readonly VivaCheckoutProvider $viva,
        private readonly StripeCheckoutProvider $stripe,
        private readonly EurobankCheckoutProvider $eurobank,
    ) {}

    /**
     * @return list<array{id: string, label: string}>
     */
    public function readyProviders(?ClubSetting $settings): array
    {
        if (! $settings) {
            return [];
        }

        $out = [];
        if ($this->providerEnabled($settings->viva ?? null, ['clientId', 'clientSecret', 'sourceCode'])) {
            $out[] = ['id' => 'viva', 'label' => 'Viva Wallet'];
        }
        if ($this->providerEnabled($settings->stripe ?? null, ['secretKey'])) {
            $out[] = ['id' => 'stripe', 'label' => 'Stripe'];
        }
        if ($this->providerEnabled($settings->eurobank ?? null, ['merchantId'], ['secretKey', 'secret'])) {
            $out[] = ['id' => 'eurobank', 'label' => 'Eurobank'];
        }

        return $out;
    }

    /**
     * @param  array{athlete: Athlete, provider: string, amountEuro: float, customerEmail?: string, customerName?: string, successUrl: string, cancelUrl: string, description?: string}  $input
     * @return array{ok: true, provider: string, orderCode: string, checkoutUrl: string, method: string, fields: ?array<string, string>, checkoutId: string}
     */
    public function start(Club $club, ClubSetting $settings, array $input): array
    {
        $provider = strtolower(trim((string) ($input['provider'] ?? '')));
        $amountEuro = round((float) ($input['amountEuro'] ?? 0), 2);
        $amountCents = (int) round($amountEuro * 100);

        if ($amountCents < 30) {
            throw new InvalidArgumentException('Το ποσό πρέπει να είναι τουλάχιστον 0,30 €.');
        }

        $athlete = $input['athlete'];
        $description = (string) ($input['description'] ?? ('Οφειλή — '.$athlete->last_name.' '.$athlete->first_name));
        $email = trim((string) ($input['customerEmail'] ?? ''));
        $name = trim((string) ($input['customerName'] ?? ($athlete->last_name.' '.$athlete->first_name)));
        $successUrl = (string) $input['successUrl'];
        $cancelUrl = (string) $input['cancelUrl'];

        $result = match ($provider) {
            'viva' => $this->viva->createOrder(
                $this->channel($settings, 'viva'),
                $amountCents,
                $email,
                $name,
                $description,
            ),
            'stripe' => $this->stripe->createSession(
                $this->channel($settings, 'stripe'),
                $amountCents,
                $email,
                $description,
                $successUrl,
                $cancelUrl,
            ),
            'eurobank' => $this->eurobank->createPayment(
                $this->channel($settings, 'eurobank'),
                $amountCents,
                $email,
                $description,
                $successUrl,
                $cancelUrl,
            ),
            default => throw new InvalidArgumentException('Άγνωστος πάροχος πληρωμής.'),
        };

        $checkout = OnlineCheckout::query()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'athlete_id' => $athlete->id,
            'provider' => $provider,
            'order_code' => (string) $result['orderCode'],
            'amount' => $amountEuro,
            'status' => 'pending',
            'idempotency_key' => $provider.':'.Str::uuid(),
            'payload' => [
                'athleteName' => trim($athlete->last_name.' '.$athlete->first_name),
                'customerEmail' => $email,
                'customerFullName' => $name,
                'amountCents' => $amountCents,
                'merchantTrns' => $description,
                'successUrl' => $successUrl,
                'cancelUrl' => $cancelUrl,
                'providerResponse' => $result['raw'] ?? [],
            ],
        ]);

        return [
            'ok' => true,
            'provider' => $provider,
            'orderCode' => (string) $result['orderCode'],
            'checkoutUrl' => (string) $result['checkoutUrl'],
            'method' => (string) ($result['method'] ?? 'GET'),
            'fields' => $result['fields'] ?? null,
            'checkoutId' => $checkout->id,
        ];
    }

    /**
     * @param  list<string>  $required
     * @param  list<string>  $anyOfSecrets
     */
    private function providerEnabled(?array $channel, array $required, array $anyOfSecrets = []): bool
    {
        if (! is_array($channel) || empty($channel['enabled'])) {
            return false;
        }

        foreach ($required as $key) {
            $value = trim((string) ($channel[$key] ?? ''));
            if ($value === '' || $value === '********') {
                return false;
            }
        }

        if ($anyOfSecrets !== []) {
            $ok = false;
            foreach ($anyOfSecrets as $key) {
                $value = trim((string) ($channel[$key] ?? ''));
                if ($value !== '' && $value !== '********') {
                    $ok = true;
                    break;
                }
            }
            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function channel(ClubSetting $settings, string $key): array
    {
        $channel = $settings->{$key} ?? null;
        if (! is_array($channel) || empty($channel['enabled'])) {
            throw new RuntimeException('Ο πάροχος δεν είναι ενεργός στις ρυθμίσεις συλλόγου.');
        }

        return $channel;
    }
}
