<?php

namespace App\Payments\Gateways\OmPay;

use App\Logging\GatewayLogger;
use App\Models\PaymentTransaction;
use App\Payments\Contracts\HandlesWebhooks;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Data\CheckoutRequest;
use App\Payments\Data\CheckoutSession;
use App\Payments\Data\CredentialCheck;
use App\Payments\Data\SettingField;
use App\Payments\Exceptions\PaymentException;
use App\Payments\PaymentStatus;

/**
 * OmPay / TruePay (Oman), bank-hosted checkout: the shopper pays on OmPay's
 * page and is sent back to return_url with only the transaction id, so the
 * outcome is always read with a server-side inquiry. Amounts are decimal OMR
 * (3 decimals); other store currencies need the merchant's exchange rate.
 *
 * Docs: https://developer.truepay.ompay.om/docs
 */
class OmPayGateway implements HandlesWebhooks, PaymentGateway
{
    /** OmPay rejects webhook signatures older (or newer) than this. */
    private const WEBHOOK_TOLERANCE = 300;

    public function __construct(private readonly GatewayLogger $logger) {}

    public function key(): string
    {
        return 'ompay';
    }

    public function name(): string
    {
        return 'OmPay';
    }

    public function settingsFields(): array
    {
        return [
            new SettingField('testMode', 'Mode', 'radio', default: 'true',
                options: ['true' => 'Test (sandbox)', 'false' => 'Live'],
                help: 'Test mode uses your OmPay sandbox keys and moves no money. Live mode takes real payments with your production keys.'),
            new SettingField('sandbox_api_key', 'API Key', group: 'Sandbox keys (test mode)', activeWhen: 'true'),
            new SettingField('sandbox_api_secret', 'API Secret', 'password', group: 'Sandbox keys (test mode)', activeWhen: 'true'),
            new SettingField('api_key', 'API Key', group: 'Production keys (live mode)', activeWhen: 'false'),
            new SettingField('api_secret', 'API Secret', 'password', group: 'Production keys (live mode)', activeWhen: 'false'),
            new SettingField('money_exchange', 'Exchange Rate', 'text',
                help: 'OmPay charges in Omani Rial. If your store uses another currency, enter the rate to convert it to OMR (for example 0.385 for USD).'),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{0: string, 1: string} API key and secret for the mode
     */
    public static function keys(array $settings, ?bool $testMode = null): array
    {
        $prefix = ($testMode ?? self::isTestMode($settings)) ? 'sandbox_' : '';

        return [trim((string) ($settings[$prefix.'api_key'] ?? '')), trim((string) ($settings[$prefix.'api_secret'] ?? ''))];
    }

    /**
     * Ecwid app storage keeps radio values as the strings "true"/"false".
     *
     * @param  array<string, mixed>  $settings
     */
    public static function isTestMode(array $settings): bool
    {
        return filter_var($settings['testMode'] ?? 'true', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $settings = $request->settings;
        $rate = $this->exchangeRate($request->currency, $settings);
        $baisa = (int) ceil(round($request->total * $rate * 1000, 6));

        $response = $this->client($settings)->createBankHosted([
            'amount' => round($baisa / 1000, 3),
            'currency' => 'OMR',
            'return_url' => $request->successUrl,
            // Unique per attempt: OmPay answers 409 to a reference it has seen.
            'reference_number' => $request->transaction->public_id,
        ]);

        $data = $response->data();
        $transactionId = (string) ($data['transaction_id'] ?? '');
        $redirectUrl = (string) ($data['redirect_url'] ?? '');

        if (! $response->ok() || $transactionId === '' || $redirectUrl === '') {
            throw new PaymentException('OmPay could not start the payment: '.$this->checkoutError($response));
        }

        return new CheckoutSession($redirectUrl, $transactionId, [
            'amount' => $baisa,
            'currency' => 'OMR',
            'exchange_rate' => $rate,
            'test_mode' => self::isTestMode($settings),
        ]);
    }

    public function fetchStatus(PaymentTransaction $transaction): PaymentStatus
    {
        $response = $this->client($transaction->gateway_settings ?? [])->inquiry((string) $transaction->gateway_reference);

        if (! $response->ok()) {
            // settle() treats this as "not known yet" and tries again later.
            throw new PaymentException('OmPay inquiry failed: '.$response->errorMessage());
        }

        $data = $response->data();

        return match (strtoupper(trim((string) ($data['status'] ?? '')))) {
            'SUCCESSFUL' => PaymentStatus::Paid,
            // FAILED with outcome_unconfirmed: OmPay never heard back from the
            // bank and it may still turn SUCCESSFUL, so keep checking.
            'FAILED' => empty($data['outcome_unconfirmed']) ? PaymentStatus::Failed : PaymentStatus::Pending,
            'REVERSED' => PaymentStatus::Failed,
            default => PaymentStatus::Pending, // PENDING, PROCESSING
        };
    }

    public function verifyCredentials(array $settings): CredentialCheck
    {
        $testMode = self::isTestMode($settings);
        [$key, $secret] = self::keys($settings, $testMode);
        $label = $testMode ? 'sandbox' : 'production';

        if ($key === '' || $secret === '') {
            return new CredentialCheck(false, "Enter both $label keys (API Key and API Secret), or switch the mode.");
        }

        $error = $this->keyError($key, $secret, $testMode);

        // Also check the other pair when one is entered, so it is known to work before switching.
        [$otherKey, $otherSecret] = self::keys($settings, ! $testMode);
        if ($otherKey === '' || $otherSecret === '') {
            return $error === null
                ? new CredentialCheck(true, 'Your OmPay API keys are working'.($testMode ? ' (test mode)' : '').'.')
                : new CredentialCheck(false, $error);
        }

        $otherError = $this->keyError($otherKey, $otherSecret, ! $testMode);

        return new CredentialCheck($error === null && $otherError === null, implode("\n", [
            ucfirst($label).' keys (in use): '.($error ?? 'working.'),
            ($testMode ? 'Production' : 'Sandbox').' keys: '.($otherError ?? 'working.'),
        ]));
    }

    /** Null when OmPay accepts the keys, otherwise why not. Reads one transaction; creates nothing. */
    private function keyError(string $key, string $secret, bool $testMode): ?string
    {
        $response = $this->makeClient($key, $secret, $testMode)->listTransactions(['limit' => 1]);

        if ($response->ok()) {
            return null;
        }

        return match ($response->error()) {
            'INVALID_CREDENTIALS', 'UNAUTHORIZED' => 'OmPay did not accept these keys. Check them, and that they are '.($testMode ? 'sandbox' : 'production').' keys (each environment has its own).',
            'IP_NOT_ALLOWED' => 'OmPay blocked this server by IP whitelist. Allow our server\'s IP address in the OmPay merchant portal, or turn the whitelist off.',
            default => $response->errorMessage(),
        };
    }

    private function checkoutError(OmPayResponse $response): string
    {
        return match ($response->error()) {
            'MERCHANT_CAPABILITY_DISABLED' => 'bank-hosted payments are not enabled on this OmPay account.',
            'INVALID_CREDENTIALS', 'UNAUTHORIZED' => 'the store\'s OmPay keys were not accepted.',
            default => $response->errorMessage(),
        };
    }

    public function webhookInstructions(): string
    {
        return 'In the OmPay merchant portal (Account → Webhooks), set this URL and enable the payment events. '
            .'OmPay signs webhooks with your API Secret, so paste the API Secret of the mode you are using below '
            .'(sandbox secret for test mode, production secret for live mode; update it when you switch). '
            .'Orders are then marked paid even if the shopper closes the page before returning to the store.';
    }

    /**
     * OmPay-Signature is "t=<unix seconds>,v1=<hex>", where
     * v1 = HMAC-SHA256(API secret, "{t}.{API key}.{raw body}") and the API
     * key is the one OmPay sends in OmPay-API-Key.
     */
    public function verifyWebhook(string $rawBody, array $headers, string $secret): bool
    {
        $apiKey = trim($headers['ompay-api-key'] ?? '');
        $parts = [];
        foreach (explode(',', (string) ($headers['ompay-signature'] ?? '')) as $part) {
            [$name, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$name] ??= $value;
        }
        $timestamp = $parts['t'] ?? '';
        $signature = strtolower($parts['v1'] ?? '');

        if ($secret === '' || $apiKey === '' || $signature === '' || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(now()->getTimestamp() - (int) $timestamp) > self::WEBHOOK_TOLERANCE) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp.'.'.$apiKey.'.'.$rawBody, $secret), $signature);
    }

    /**
     * Only payment events point at our transaction; refund and reversal
     * events carry the refund's own id, and webhook.test carries none.
     */
    public function webhookReference(array $payload): ?string
    {
        if (! str_starts_with((string) ($payload['type'] ?? ''), 'payment.')) {
            return null;
        }

        $id = $payload['data']['transaction_id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function client(array $settings): OmPayClient
    {
        [$key, $secret] = self::keys($settings);

        if ($key === '' || $secret === '') {
            throw new PaymentException('OmPay is not set up yet: the store owner needs to enter the API keys in the app settings.');
        }

        return $this->makeClient($key, $secret, self::isTestMode($settings));
    }

    private function makeClient(string $key, string $secret, bool $sandbox): OmPayClient
    {
        return new OmPayClient($this->logger, $key, $secret, $sandbox);
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

        throw new PaymentException('OmPay only accepts Omani Rial (OMR). The store owner needs to set an exchange rate in the OmPay app settings.');
    }
}
