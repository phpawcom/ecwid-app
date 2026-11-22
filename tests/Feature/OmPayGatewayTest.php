<?php

namespace Tests\Feature;

use App\Ecwid\EcwidCrypto;
use App\Licensing\LicenseResult;
use App\Licensing\WhmcsLicenseChecker;
use App\Models\EcwidApp;
use App\Models\EcwidStore;
use App\Models\GatewayLog;
use App\Models\PaymentTransaction;
use App\Payments\Data\CheckoutRequest;
use App\Payments\Exceptions\PaymentException;
use App\Payments\Gateways\OmPay\OmPayGateway;
use App\Payments\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class OmPayGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const SANDBOX = 'https://api.sandbox.truepay.ompay.om';

    private const SETTINGS = ['testMode' => 'true', 'sandbox_api_key' => 'sk_test', 'sandbox_api_secret' => 'ss_test', 'api_key' => 'pk_live', 'api_secret' => 'ps_live'];

    private const BANK_HOSTED = self::SANDBOX.'/api/v1/transactions/bank-hosted';

    private const INQUIRY = self::SANDBOX.'/api/v1/transactions/inquiry';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function gateway(): OmPayGateway
    {
        return app(OmPayGateway::class);
    }

    private function request(float $total, string $currency = 'OMR', array $settings = []): CheckoutRequest
    {
        $transaction = new PaymentTransaction(['store_id' => 1003]);
        $transaction->id = 7;
        $transaction->public_id = '01JTESTULID0000000000000000';

        return new CheckoutRequest(
            transaction: $transaction, total: $total, currency: $currency, items: [],
            settings: $settings + self::SETTINGS,
            successUrl: 'https://app.test/pay/x/return', cancelUrl: 'https://app.test/pay/x/cancel', orderLabel: 'Order 42',
        );
    }

    private function transaction(array $settings = self::SETTINGS): PaymentTransaction
    {
        $transaction = new PaymentTransaction(['gateway_reference' => 'txn_1']);
        $transaction->gateway_settings = $settings;

        return $transaction;
    }

    public function test_checkout_creates_bank_hosted_payment_in_omr(): void
    {
        Http::fake([self::BANK_HOSTED => Http::response(['success' => true, 'data' => [
            'transaction_id' => 'txn_1', 'status' => 'PROCESSING', 'redirect_url' => self::SANDBOX.'/api/v1/transactions/txn_1/pay-page',
        ]])]);

        $session = $this->gateway()->createCheckout($this->request(15.75));

        $this->assertSame(self::SANDBOX.'/api/v1/transactions/txn_1/pay-page', $session->redirectUrl);
        $this->assertSame('txn_1', $session->reference);
        $this->assertSame(15750, $session->meta['amount']);
        Http::assertSent(fn (Request $r) => $r->hasHeader('OMPAY-API-Key', 'sk_test') && $r->hasHeader('OMPAY-API-Secret', 'ss_test')
            && $r->body() === '{"amount":15.75,"currency":"OMR","return_url":"https://app.test/pay/x/return","reference_number":"01JTESTULID0000000000000000"}');

        $log = GatewayLog::sole();
        $this->assertSame('ompay', $log->service);
        $this->assertStringStartsWith('••••', $log->request_headers['ompay-api-secret']);
    }

    public function test_live_mode_uses_production_url_and_keys(): void
    {
        Http::fake(['https://api.truepay.ompay.om/api/v1/transactions/bank-hosted' => Http::response(['success' => true, 'data' => ['transaction_id' => 't', 'redirect_url' => 'https://x.test']])]);

        $this->gateway()->createCheckout($this->request(1, settings: ['testMode' => 'false']));

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://api.truepay.ompay.om/api/v1/') && $r->hasHeader('OMPAY-API-Key', 'pk_live'));
    }

    public function test_other_currency_is_converted_and_rounded_up_to_a_baisa(): void
    {
        Http::fake([self::BANK_HOSTED => Http::response(['success' => true, 'data' => ['transaction_id' => 't', 'redirect_url' => 'https://x.test']])]);

        $session = $this->gateway()->createCheckout($this->request(10.0001, 'USD', ['money_exchange' => '0.385']));

        $this->assertSame(3851, $session->meta['amount']);
        Http::assertSent(fn (Request $r) => $r['amount'] === 3.851);
    }

    public function test_other_currency_without_exchange_rate_is_refused_before_calling_ompay(): void
    {
        Http::fake();

        try {
            $this->gateway()->createCheckout($this->request(10, 'USD'));
            $this->fail('Expected a PaymentException.');
        } catch (PaymentException $e) {
            $this->assertStringContainsString('needs to set an exchange rate', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_checkout_errors_are_explained(): void
    {
        Http::fake([self::BANK_HOSTED => Http::response(['success' => false, 'error' => 'MERCHANT_CAPABILITY_DISABLED', 'message' => 'Capability disabled'], 402)]);

        $this->expectExceptionMessage('OmPay could not start the payment: bank-hosted payments are not enabled on this OmPay account.');
        $this->gateway()->createCheckout($this->request(1));
    }

    public function test_checkout_without_keys_is_refused_before_calling_ompay(): void
    {
        Http::fake();

        try {
            $this->gateway()->createCheckout($this->request(1, settings: ['sandbox_api_key' => '']));
            $this->fail('Expected a PaymentException.');
        } catch (PaymentException $e) {
            $this->assertStringContainsString('OmPay is not set up yet', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_status_mapping(): void
    {
        $cases = [
            [['status' => 'SUCCESSFUL'], PaymentStatus::Paid],
            [['status' => 'FAILED'], PaymentStatus::Failed],
            [['status' => 'FAILED', 'outcome_unconfirmed' => true], PaymentStatus::Pending],
            [['status' => 'PROCESSING'], PaymentStatus::Pending],
            [['status' => 'PENDING'], PaymentStatus::Pending],
        ];

        $sequence = Http::sequence();
        foreach ($cases as [$data]) {
            $sequence->push(['success' => true, 'data' => $data + ['transaction_id' => 'txn_1']]);
        }
        Http::fake([self::INQUIRY => $sequence]);

        foreach ($cases as [$data, $expected]) {
            $this->assertSame($expected, $this->gateway()->fetchStatus($this->transaction()), json_encode($data));
        }

        Http::assertSent(fn (Request $r) => $r['transaction_id'] === 'txn_1');
    }

    public function test_failed_inquiry_throws_so_the_payment_stays_pending(): void
    {
        Http::fake([self::INQUIRY => Http::response(['success' => false, 'error' => 'TRANSACTION_LOOKUP_UNAVAILABLE', 'message' => 'Try again'], 503)]);

        $this->expectException(PaymentException::class);
        $this->gateway()->fetchStatus($this->transaction());
    }

    public function test_verify_credentials(): void
    {
        Http::fake([
            self::SANDBOX.'/api/v1/transactions?limit=1' => Http::response(['success' => true, 'data' => [], 'pagination' => ['total' => 0]]),
            'https://api.truepay.ompay.om/api/v1/transactions?limit=1' => Http::response(['success' => false, 'error' => 'INVALID_CREDENTIALS', 'message' => 'Invalid'], 401),
        ]);

        $check = $this->gateway()->verifyCredentials(self::SETTINGS);
        $this->assertFalse($check->ok);
        $this->assertStringContainsString('Sandbox keys (in use): working.', $check->message);
        $this->assertStringContainsString('Production keys: OmPay did not accept these keys', $check->message);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && str_ends_with($r->url(), '/api/v1/transactions?limit=1'));

        $this->assertTrue($this->gateway()->verifyCredentials(['api_key' => '', 'api_secret' => ''] + self::SETTINGS)->ok);
        $this->assertFalse($this->gateway()->verifyCredentials(['testMode' => 'true'])->ok);
    }

    public function test_webhook_signature(): void
    {
        $this->travelTo('2026-11-22 12:00:00');
        $gateway = $this->gateway();
        $body = '{"id":"evt_1","type":"payment.succeeded","data":{"transaction_id":"txn_1"}}';
        $t = '1795348800';
        $sign = fn (string $t, string $key = 'sk_test', string $secret = 'ss_test'): string => "t=$t,v1=".hash_hmac('sha256', "$t.$key.$body", $secret);

        $this->assertTrue($gateway->verifyWebhook($body, ['ompay-signature' => $sign($t), 'ompay-api-key' => 'sk_test'], 'ss_test'));
        $this->assertFalse($gateway->verifyWebhook($body, ['ompay-signature' => $sign($t), 'ompay-api-key' => 'sk_test'], 'other'));
        $this->assertFalse($gateway->verifyWebhook($body, ['ompay-signature' => $sign($t), 'ompay-api-key' => 'pk_live'], 'ss_test'));
        $this->assertFalse($gateway->verifyWebhook($body.' ', ['ompay-signature' => $sign($t), 'ompay-api-key' => 'sk_test'], 'ss_test'));
        $old = '1795348200';
        $this->assertFalse($gateway->verifyWebhook($body, ['ompay-signature' => $sign($old), 'ompay-api-key' => 'sk_test'], 'ss_test'));
        $this->assertFalse($gateway->verifyWebhook($body, ['ompay-api-key' => 'sk_test'], 'ss_test'));
    }

    public function test_webhook_reference_only_for_payment_events(): void
    {
        $gateway = $this->gateway();

        $this->assertSame('txn_1', $gateway->webhookReference(['type' => 'payment.failed', 'data' => ['transaction_id' => 'txn_1']]));
        $this->assertNull($gateway->webhookReference(['type' => 'refund.succeeded', 'data' => ['transaction_id' => 'rf_1']]));
        $this->assertNull($gateway->webhookReference(['type' => 'webhook.test', 'data' => []]));
    }

    public function test_signed_webhook_settles_the_order(): void
    {
        $app = EcwidApp::create(['client_id' => 'ompay-gateway', 'client_secret' => str_repeat('a', 32), 'gateway' => 'ompay', 'name' => 'OmPay']);
        $store = EcwidStore::create(['ecwid_app_id' => $app->id, 'store_id' => 1003, 'webhook_secret' => 'ss_test']);
        $transaction = PaymentTransaction::create([
            'ecwid_app_id' => $app->id, 'store_id' => 1003, 'gateway' => 'ompay', 'order_id' => 'transaction_42',
            'order_number' => 'XJ12H', 'amount' => 1.5, 'currency' => 'OMR', 'ecwid_token' => 'secret_token',
            'return_url' => 'https://app.ecwid.com/custompaymentapps/1003', 'gateway_reference' => 'txn_1',
            'gateway_settings' => self::SETTINGS,
        ]);
        Http::fake([
            self::INQUIRY => Http::response(['success' => true, 'data' => ['transaction_id' => 'txn_1', 'status' => 'SUCCESSFUL']]),
            'app.ecwid.com/api/v3/1003/orders/transaction_42' => Http::response(['updateCount' => 1]),
        ]);

        $body = json_encode(['id' => 'evt_1', 'type' => 'payment.succeeded', 'data' => ['transaction_id' => 'txn_1', 'status' => 'SUCCESSFUL']]);
        $this->travelTo('2026-11-22 12:00:00');
        $t = '1795348800';

        $this->call('POST', '/webhooks/'.$store->webhook_token, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_OMPAY_SIGNATURE' => "t=$t,v1=".hash_hmac('sha256', "$t.sk_test.$body", 'ss_test'),
            'HTTP_OMPAY_API_KEY' => 'sk_test',
        ], $body)->assertOk()->assertJson(['message' => 'Payment paid.']);

        $this->assertSame(PaymentStatus::Paid, $transaction->fresh()->status);
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r['paymentStatus'] === 'PAID');
    }

    public function test_settings_page_renders_for_ompay(): void
    {
        $checker = Mockery::mock(WhmcsLicenseChecker::class);
        $checker->shouldReceive('check')->andReturn(LicenseResult::fromWhmcs(['status' => 'Active', 'addons' => 'name=OmPay;status=Active']));
        $this->app->instance(WhmcsLicenseChecker::class, $checker);
        EcwidApp::create(['client_id' => 'ompay-gateway', 'client_secret' => str_repeat('a', 32), 'gateway' => 'ompay', 'name' => 'OmPay']);
        Http::fake(['app.ecwid.com/api/v3/1003/*' => Http::response([])]);

        $payload = EcwidCrypto::encrypt(str_repeat('a', 32), ['store_id' => 1003, 'access_token' => 'secret_admin'], 'aes-128-cbc');
        $html = $this->get('/ecwid/ompay-gateway/settings?payload='.$payload)->assertOk()
            ->assertSee('OmPay payments')
            ->assertSee('Sandbox keys (test mode)')
            ->assertSee('Developer → Credentials', false)
            ->assertSee('Paste the secret from OmPay')
            ->getContent();

        foreach (['sandbox_api_key', 'sandbox_api_secret', 'api_key', 'api_secret', 'money_exchange'] as $key) {
            $this->assertStringContainsString($key, $html);
        }
        $this->assertStringContainsString('ompay-512x512.png', $html);
    }

    public function test_storefront_script_shows_ompay_logos(): void
    {
        EcwidApp::create(['client_id' => 'ompay-gateway', 'client_secret' => str_repeat('a', 32), 'gateway' => 'ompay', 'name' => 'OmPay']);

        $this->get('/ecwid/ompay-gateway/storefront.js')->assertOk()
            ->assertSee('ompay-54x34.png', false)->assertSee('visa.svg', false)->assertDontSee('thawani', false);
    }
}
