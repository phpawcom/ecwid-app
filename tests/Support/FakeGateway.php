<?php

namespace Tests\Support;

use App\Models\PaymentTransaction;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Data\CheckoutRequest;
use App\Payments\Data\CheckoutSession;
use App\Payments\Data\CredentialCheck;
use App\Payments\Data\SettingField;
use App\Payments\PaymentStatus;

class FakeGateway implements PaymentGateway
{
    public static PaymentStatus $status = PaymentStatus::Paid;

    public static ?CheckoutRequest $lastRequest = null;

    public static int $statusChecks = 0;

    public function key(): string
    {
        return 'fake';
    }

    public function name(): string
    {
        return 'Fake Pay';
    }

    public function settingsFields(): array
    {
        return [
            new SettingField('api_key', 'API Key', required: true),
            new SettingField('sandbox', 'Sandbox', 'checkbox', default: 'true'),
        ];
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        self::$lastRequest = $request;

        return new CheckoutSession('https://pay.example/session/abc', 'sess_abc', ['amount' => 1234, 'currency' => 'OMR']);
    }

    public function fetchStatus(PaymentTransaction $transaction): PaymentStatus
    {
        self::$statusChecks++;

        return self::$status;
    }

    public function verifyCredentials(array $settings): CredentialCheck
    {
        return ($settings['api_key'] ?? '') === 'good'
            ? new CredentialCheck(true, 'Keys work.')
            : new CredentialCheck(false, 'Bad key.');
    }
}
