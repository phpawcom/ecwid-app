<?php

namespace Tests\Feature;

use App\Logging\GatewayLogger;
use App\Models\EcwidApp;
use App\Models\GatewayLog;
use App\Models\PaymentTransaction;
use App\Payments\Data\CheckoutRequest;
use App\Payments\Gateways\Thawani\ThawaniGateway;
use App\Payments\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Runs the real package client through our HTTP layer (no overridden API methods).
 */
class ThawaniHttpLoggingTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://uatcheckout.thawani.om/api/v1/';

    private PaymentTransaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        $app = EcwidApp::create(['client_id' => 'thawani-gateway', 'client_secret' => str_repeat('s', 32), 'gateway' => 'thawani', 'name' => 'Thawani']);
        $this->transaction = PaymentTransaction::create([
            'ecwid_app_id' => $app->id, 'store_id' => 1003, 'gateway' => 'thawani', 'order_id' => 'transaction_42',
            'amount' => 1.5, 'currency' => 'OMR', 'ecwid_token' => 't', 'return_url' => 'https://example.test',
            'gateway_settings' => ['uat_publishable_key' => 'pk_live_1234', 'uat_secret_key' => 'sk_secret_5678', 'testMode' => 'true'],
        ]);

    }

    private function fakeThawani(): void
    {
        Http::fake([
            self::API.'checkout/session' => Http::response(['success' => true, 'code' => 2004, 'data' => ['session_id' => 'checkout_x']]),
            self::API.'checkout/session/checkout_x' => Http::response(['success' => true, 'data' => ['session_id' => 'checkout_x', 'payment_status' => 'paid']]),
        ]);
    }

    private function inContext(\Closure $callback): mixed
    {
        return app(GatewayLogger::class)->withContext([
            'ecwid_app_id' => $this->transaction->ecwid_app_id,
            'store_id' => 1003,
            'payment_transaction_id' => $this->transaction->id,
            'operation' => 'checkout.create',
        ], $callback);
    }

    public function test_checkout_goes_through_the_package_and_is_logged(): void
    {
        $this->fakeThawani();
        $session = $this->inContext(fn () => app(ThawaniGateway::class)->createCheckout(new CheckoutRequest(
            transaction: $this->transaction, total: 1.5, currency: 'OMR', items: [],
            settings: $this->transaction->gateway_settings,
            successUrl: 'https://app.test/ok', cancelUrl: 'https://app.test/cancel', orderLabel: 'Order 42',
            customerName: 'Shopper',
        )));

        $this->assertSame('checkout_x', $session->reference);
        $this->assertSame('https://uatcheckout.thawani.om/pay/checkout_x?key=pk_live_1234', $session->redirectUrl);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::API.'checkout/session'
            && $r->hasHeader('Thawani-Api-Key', 'sk_secret_5678')
            && $r->hasHeader('Content-Type', 'application/json')
            && $r['products'][0]['unit_amount'] === 1500
            && $r['success_url'] === 'https://app.test/ok');

        $log = GatewayLog::sole();
        $this->assertSame('thawani', $log->service);
        $this->assertSame('checkout.create', $log->operation);
        $this->assertSame($this->transaction->id, $log->payment_transaction_id);
        $this->assertSame(1003, $log->store_id);
        $this->assertTrue($log->success);
        $this->assertSame(200, $log->response_status);
        $this->assertSame('••••5678', $log->request_headers['thawani-api-key']);
        $this->assertStringNotContainsString('sk_secret_5678', json_encode($log->getAttributes()));
        $this->assertSame('Shopper', json_decode($log->request_body, true)['metadata']['customer_name']);
        $this->assertStringContainsString('checkout_x', $log->response_body);
    }

    public function test_status_check_is_logged(): void
    {
        $this->fakeThawani();
        $this->transaction->update(['gateway_reference' => 'checkout_x']);

        $status = app(ThawaniGateway::class)->fetchStatus($this->transaction);

        $this->assertSame(PaymentStatus::Paid, $status);
        $log = GatewayLog::sole();
        $this->assertSame('GET', $log->method);
        $this->assertSame(self::API.'checkout/session/checkout_x', $log->url);
        $this->assertNull($log->request_body);
    }

    public function test_thawani_error_is_logged_as_failed(): void
    {
        Http::fake([self::API.'*' => Http::response(['success' => false, 'code' => 4001, 'description' => 'Invalid API key'], 401)]);

        $check = app(ThawaniGateway::class)->verifyCredentials(['uat_publishable_key' => 'pk', 'uat_secret_key' => 'sk_bad_key', 'testMode' => 'true']);

        $this->assertFalse($check->ok);
        $log = GatewayLog::sole();
        $this->assertFalse($log->success);
        $this->assertSame(401, $log->response_status);
        $this->assertSame('Invalid API key', $log->error);
    }
}
