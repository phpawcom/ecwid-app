<?php

namespace Tests\Feature;

use App\Models\PaymentTransaction;
use App\Payments\Data\CheckoutRequest;
use App\Payments\Data\LineItem;
use App\Payments\Exceptions\PaymentException;
use App\Payments\Gateways\Thawani\ThawaniClientFactory;
use App\Payments\Gateways\Thawani\ThawaniGateway;
use App\Payments\PaymentStatus;
use S4D\Laravel\Thawani\Services\ThawaniService;
use Tests\TestCase;

class ThawaniGatewayTest extends TestCase
{
    /** Stands in for the package client so no request reaches Thawani. */
    private ThawaniService $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new class extends ThawaniService
        {
            public array $sent = [];

            public array $session = [];

            public bool $fail = false;

            public function generatePaymentUrl(array $input)
            {
                $this->sent = $input;
                if ($this->fail) {
                    $this->responseData = ['success' => false, 'description' => 'Invalid API key'];

                    return '';
                }
                $this->payment_id = 'checkout_123';

                return 'https://uatcheckout.thawani.om/pay/checkout_123?key='.$this->config['public_key'];
            }

            public function checkPaymentStatus($session_id)
            {
                return $this->session;
            }
        };

        $factory = new class($this->client) extends ThawaniClientFactory
        {
            public array $made = [];

            public function __construct(private ThawaniService $client) {}

            public function make(string $publishableKey, string $secretKey, bool $testMode): ThawaniService
            {
                $this->made = compact('publishableKey', 'secretKey', 'testMode');
                $this->client->config = ['isTestMode' => $testMode ? 1 : 0, 'public_key' => $publishableKey, 'private_key' => $secretKey];

                return $this->client;
            }
        };

        $this->app->instance(ThawaniClientFactory::class, $factory);
    }

    private function request(float $total, string $currency, array $items, array $settings = [], bool $discount = false): CheckoutRequest
    {
        $transaction = new PaymentTransaction(['store_id' => 1003]);
        $transaction->id = 7;

        return new CheckoutRequest(
            transaction: $transaction, total: $total, currency: $currency, items: $items,
            settings: $settings + ['publishable_key' => 'pk', 'secret_key' => 'sk', 'testMode' => 'true'],
            successUrl: 'https://app.test/ok', cancelUrl: 'https://app.test/cancel', orderLabel: 'Order 42',
            hasDiscount: $discount,
        );
    }

    public function test_omr_order_is_itemised_in_baisa_with_shipping_line(): void
    {
        $session = app(ThawaniGateway::class)->createCheckout($this->request(13.6, 'OMR', [
            new LineItem('Mug', 1.1, 2),
            new LineItem('Tea', 10.0, 1),
        ]));

        $this->assertSame('checkout_123', $session->reference);
        $this->assertSame(13600, $session->meta['amount']);
        $this->assertSame([
            ['name' => 'Mug', 'unit_amount' => 1100, 'quantity' => 2],
            ['name' => 'Tea', 'unit_amount' => 10000, 'quantity' => 1],
            ['name' => 'Shipping, taxes & fees', 'unit_amount' => 1400, 'quantity' => 1],
        ], $this->client->sent['products']);
        $this->assertSame('https://app.test/ok', $this->client->sent['success_url']);
    }

    public function test_discounted_order_is_one_line(): void
    {
        app(ThawaniGateway::class)->createCheckout($this->request(9.0, 'OMR', [new LineItem('Tea', 10.0, 1)], discount: true));

        $this->assertSame([['name' => 'Order 42', 'unit_amount' => 9000, 'quantity' => 1]], $this->client->sent['products']);
    }

    public function test_items_above_total_fall_back_to_one_line(): void
    {
        app(ThawaniGateway::class)->createCheckout($this->request(9.0, 'OMR', [new LineItem('Tea', 10.0, 1)]));

        $this->assertSame([['name' => 'Order 42', 'unit_amount' => 9000, 'quantity' => 1]], $this->client->sent['products']);
    }

    public function test_other_currency_uses_exchange_rate(): void
    {
        $session = app(ThawaniGateway::class)->createCheckout($this->request(100.0, 'USD', [], ['money_exchange' => '0.385']));

        $this->assertSame(38500, $session->meta['amount']);
    }

    public function test_other_currency_without_rate_is_refused(): void
    {
        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Omani Rial');

        app(ThawaniGateway::class)->createCheckout($this->request(100.0, 'USD', []));
    }

    public function test_gateway_error_message_is_passed_on(): void
    {
        $this->client->fail = true;

        $this->expectExceptionMessage('Invalid API key');

        app(ThawaniGateway::class)->createCheckout($this->request(1.0, 'OMR', []));
    }

    public function test_test_mode_setting_values(): void
    {
        $this->assertTrue(ThawaniGateway::isTestMode(['testMode' => 'true']));
        $this->assertTrue(ThawaniGateway::isTestMode(['testMode' => 'on']));
        $this->assertTrue(ThawaniGateway::isTestMode([]));
        $this->assertFalse(ThawaniGateway::isTestMode(['testMode' => 'false']));
    }

    public function test_status_mapping(): void
    {
        $transaction = new PaymentTransaction(['gateway_settings' => ['publishable_key' => 'pk', 'secret_key' => 'sk', 'testMode' => 'false'], 'gateway_reference' => 'checkout_123']);
        $gateway = app(ThawaniGateway::class);

        $this->client->session = ['payment_status' => 'paid'];
        $this->assertSame(PaymentStatus::Paid, $gateway->fetchStatus($transaction));
        $this->client->session = ['payment_status' => 'cancelled'];
        $this->assertSame(PaymentStatus::Cancelled, $gateway->fetchStatus($transaction));
        $this->client->session = ['payment_status' => 'unpaid'];
        $this->assertSame(PaymentStatus::Pending, $gateway->fetchStatus($transaction));
        $this->client->session = [];
        $this->assertSame(PaymentStatus::Pending, $gateway->fetchStatus($transaction));
    }

    public function test_verify_credentials(): void
    {
        $gateway = app(ThawaniGateway::class);

        $this->assertTrue($gateway->verifyCredentials(['publishable_key' => 'pk', 'secret_key' => 'sk', 'testMode' => 'false'])->ok);
        $this->assertFalse($gateway->verifyCredentials(['publishable_key' => 'pk', 'testMode' => 'false'])->ok);

        $this->client->fail = true;
        $check = $gateway->verifyCredentials(['publishable_key' => 'pk', 'secret_key' => 'sk', 'testMode' => 'false']);
        $this->assertFalse($check->ok);
        $this->assertSame('Invalid API key', $check->message);
    }

    public function test_verify_checks_both_key_pairs_when_both_are_set(): void
    {
        $check = app(ThawaniGateway::class)->verifyCredentials([
            'testMode' => 'true', 'uat_publishable_key' => 'uat_pk', 'uat_secret_key' => 'uat_sk', 'publishable_key' => 'pk', 'secret_key' => 'sk',
        ]);

        $this->assertTrue($check->ok);
        $this->assertStringContainsString('UAT keys (in use): working.', $check->message);
        $this->assertStringContainsString('Production keys: working.', $check->message);
    }

    public function test_keys_by_mode(): void
    {
        // v1 stores: their single saved pair is the production pair; test mode uses Thawani's public UAT keys.
        $v1 = ['publishable_key' => 'pk', 'secret_key' => 'sk', 'testMode' => 'true'];
        $this->assertSame([ThawaniGateway::UAT_PUBLISHABLE_KEY, ThawaniGateway::UAT_SECRET_KEY], ThawaniGateway::keys($v1));
        $this->assertSame(['pk', 'sk'], ThawaniGateway::keys(['testMode' => 'false'] + $v1));

        $both = $v1 + ['uat_publishable_key' => 'uat_pk', 'uat_secret_key' => 'uat_sk'];
        $this->assertSame(['uat_pk', 'uat_sk'], ThawaniGateway::keys($both));
        $this->assertSame(['pk', 'sk'], ThawaniGateway::keys($both, testMode: false));
    }

    public function test_settings_page_defaults_uat_keys(): void
    {
        $fields = collect(app(ThawaniGateway::class)->settingsFields())->keyBy('key');

        $this->assertSame(ThawaniGateway::UAT_PUBLISHABLE_KEY, $fields['uat_publishable_key']->default);
        $this->assertSame(ThawaniGateway::UAT_SECRET_KEY, $fields['uat_secret_key']->default);
        $this->assertSame('', $fields['publishable_key']->default);
    }
}
