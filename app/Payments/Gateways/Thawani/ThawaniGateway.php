<?php

namespace App\Payments\Gateways\Thawani;

use App\Models\PaymentTransaction;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Data\CheckoutRequest;
use App\Payments\Data\CheckoutSession;
use App\Payments\Data\CredentialCheck;
use App\Payments\Data\SettingField;
use App\Payments\Exceptions\PaymentException;
use App\Payments\PaymentStatus;
use S4D\Laravel\Thawani\Services\ThawaniService;

/**
 * Thawani Checkout (Oman). Amounts are sent in baisa (1 OMR = 1000 baisa).
 * Thawani only charges in OMR, so other store currencies need the merchant's
 * exchange rate.
 */
class ThawaniGateway implements PaymentGateway
{
    public function __construct(private readonly ThawaniClientFactory $factory) {}

    public function key(): string
    {
        return 'thawani';
    }

    public function name(): string
    {
        return 'Thawani';
    }

    public function settingsFields(): array
    {
        // Keys match v1 so existing merchants keep their saved settings.
        return [
            new SettingField('publishable_key', 'Publishable Key', required: true),
            new SettingField('secret_key', 'Secret Key', 'password', required: true),
            new SettingField('money_exchange', 'Exchange Rate', 'text',
                help: 'Thawani only accepts Omani Rial. If your store uses another currency, enter the rate to convert it to OMR (for example 0.385 for USD).'),
            new SettingField('testMode', 'Enable test mode (no charges)', 'checkbox',
                help: 'Use your UAT keys while test mode is on.', default: 'true'),
        ];
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $settings = $request->settings;
        $rate = $this->exchangeRate($request->currency, $settings);
        $client = $this->client($settings);

        $total = $this->toBaisa($request->total, $rate);
        $products = $this->products($request, $rate, $total);

        $url = $client->generatePaymentUrl([
            // Thawani rejects a reference it has seen before, so add a random prefix like v1 did.
            'client_reference_id' => random_int(1000, 9999).$request->transaction->id,
            'products' => $products,
            'success_url' => $request->successUrl,
            'cancel_url' => $request->cancelUrl,
            'metadata' => array_filter([
                'order' => $request->orderLabel,
                'store_id' => (string) $request->transaction->store_id,
                'customer_name' => $request->customerName,
                'customer_phone' => $request->customerPhone,
                'customer_email' => $request->customerEmail,
            ]),
        ]);

        if (empty($url)) {
            throw new PaymentException('Thawani could not start the payment: '.self::errorMessage($client));
        }

        return new CheckoutSession($url, $client->payment_id, [
            'amount' => $total,
            'currency' => 'OMR',
            'exchange_rate' => $rate,
            'test_mode' => self::isTestMode($settings),
        ]);
    }

    public function fetchStatus(PaymentTransaction $transaction): PaymentStatus
    {
        $client = $this->client($transaction->gateway_settings ?? []);
        $session = $client->checkPaymentStatus($transaction->gateway_reference);

        return match (strtolower(trim((string) ($session['payment_status'] ?? '')))) {
            'paid' => PaymentStatus::Paid,
            'cancelled', 'canceled' => PaymentStatus::Cancelled,
            default => PaymentStatus::Pending,
        };
    }

    public function verifyCredentials(array $settings): CredentialCheck
    {
        if (empty($settings['publishable_key']) || empty($settings['secret_key'])) {
            return new CredentialCheck(false, 'Enter both the Publishable Key and the Secret Key.');
        }

        $client = $this->client($settings);
        $url = $client->generatePaymentUrl([
            'client_reference_id' => (string) random_int(100000, 999999),
            'products' => [['name' => 'API key test', 'unit_amount' => 100, 'quantity' => 1]],
            'success_url' => url('/'),
            'cancel_url' => url('/'),
            'metadata' => ['note' => 'Ecwid app settings: checking API keys'],
        ]);

        if (empty($url)) {
            return new CredentialCheck(false, self::errorMessage($client));
        }

        return new CredentialCheck(true, 'Your Thawani API keys are working'.(self::isTestMode($settings) ? ' (test mode)' : '').'.');
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function client(array $settings): ThawaniService
    {
        return $this->factory->make(
            (string) ($settings['publishable_key'] ?? ''),
            (string) ($settings['secret_key'] ?? ''),
            self::isTestMode($settings),
        );
    }

    /**
     * Ecwid app storage keeps checkbox values as the strings "true"/"false";
     * v1's settings form posted "on".
     *
     * @param  array<string, mixed>  $settings
     */
    public static function isTestMode(array $settings): bool
    {
        $value = $settings['testMode'] ?? 'true';

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function exchangeRate(string $currency, array $settings): float
    {
        if (strtoupper($currency) === 'OMR') {
            return 1.0;
        }

        $rate = $settings['money_exchange'] ?? '';
        if (is_numeric($rate) && (float) $rate > 0) {
            return (float) $rate;
        }

        throw new PaymentException('Thawani only accepts Omani Rial (OMR). The store owner needs to set an exchange rate in the Thawani app settings.');
    }

    private function toBaisa(float $amount, float $rate): int
    {
        // round() first so float noise (e.g. 1.1 * 1000 = 1100.0000000002) does not ceil up a baisa.
        return (int) ceil(round($amount * $rate * 1000, 6));
    }

    /**
     * Itemise the order when the items add up to the total; otherwise send
     * one line for the whole order (discounts, taxes and shipping make the
     * item sum differ, and Thawani has no separate fields for them).
     *
     * @return list<array{name: string, unit_amount: int, quantity: int}>
     */
    private function products(CheckoutRequest $request, float $rate, int $total): array
    {
        $single = [['name' => $request->orderLabel, 'unit_amount' => $total, 'quantity' => 1]];

        if ($request->hasDiscount || $request->items === []) {
            return $single;
        }

        $products = [];
        $sum = 0;
        foreach ($request->items as $item) {
            $unit = $this->toBaisa($item->unitPrice, $rate);
            $products[] = ['name' => $item->name, 'unit_amount' => $unit, 'quantity' => max(1, $item->quantity)];
            $sum += $unit * max(1, $item->quantity);
        }

        // Shipping, handling, taxes: add the difference as one extra line.
        if ($total > $sum) {
            $products[] = ['name' => 'Shipping, taxes & fees', 'unit_amount' => $total - $sum, 'quantity' => 1];
            $sum = $total;
        }

        return $sum === $total ? $products : $single;
    }

    private static function errorMessage(ThawaniService $client): string
    {
        $data = $client->responseData;

        if (is_array($data) && $data !== []) {
            foreach (['detail', 'description', 'message', 'error'] as $key) {
                if (! empty($data[$key]) && is_string($data[$key])) {
                    return $data[$key];
                }
            }

            if (isset($data['data']) && is_array($data['data'])) {
                $lines = [];
                foreach ($data['data'] as $field => $errors) {
                    foreach ((array) $errors as $error) {
                        if (is_string($error)) {
                            $lines[] = is_string($field) ? "$field: $error" : $error;
                        }
                    }
                }
                if ($lines !== []) {
                    return implode("\n", $lines);
                }
            }
        }

        return 'Thawani did not accept the request. Check the keys and whether test mode matches them.';
    }
}
