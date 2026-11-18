<?php

namespace Tests\Feature;

use App\Ecwid\EcwidCrypto;
use App\Licensing\LicenseResult;
use App\Licensing\WhmcsLicenseChecker;
use App\Models\EcwidApp;
use App\Models\EcwidStore;
use App\Models\GatewayLog;
use App\Models\PaymentTransaction;
use App\Payments\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'abcdefghijklmnop0123456789ABCDEF';

    private EcwidApp $ecwidApp;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.gateways.fake' => FakeGateway::class,
            'licensing.gateways.fake' => ['addons' => ['Fake']],
        ]);
        FakeGateway::$status = PaymentStatus::Paid;
        FakeGateway::$lastRequest = null;
        FakeGateway::$statusChecks = 0;

        $this->ecwidApp = EcwidApp::create([
            'client_id' => 'fake-app', 'client_secret' => self::SECRET, 'gateway' => 'fake', 'name' => 'Fake', 'legacy_path' => '.',
        ]);

        $this->license('Active', 'name=Fake Gateway;status=Active');
        Http::fake(['app.ecwid.com/*' => Http::response(['updateCount' => 1])]);
    }

    private function license(string $status, string $addons = ''): void
    {
        $checker = Mockery::mock(WhmcsLicenseChecker::class);
        $checker->shouldReceive('check')->andReturn(LicenseResult::fromWhmcs(['status' => $status, 'addons' => $addons]));
        $this->app->instance(WhmcsLicenseChecker::class, $checker);
    }

    /** @return array<string, mixed> */
    private function ecwidRequest(array $overrides = []): array
    {
        return array_replace_recursive([
            'storeId' => 1003,
            'token' => 'secret_token',
            'returnUrl' => 'https://app.ecwid.com/custompaymentapps/1003?orderId=XJ12H&clientId=fake-app&timestamp=1&key=k',
            'merchantAppSettings' => ['api_key' => 'good', 's4d_license' => 'ECWID-ABC'],
            'cart' => [
                'currency' => 'OMR',
                'order' => [
                    'id' => 'XJ12H', 'orderNumber' => 42, 'referenceTransactionId' => 'transaction_42',
                    'total' => 12.5, 'email' => 'shopper@example.com',
                    'billingPerson' => ['name' => 'Shopper', 'phone' => '99999999'],
                    'items' => [['name' => 'Mug', 'price' => 6.25, 'quantity' => 2]],
                ],
            ],
        ], $overrides);
    }

    private function postPayment(array $payload, string $url = '/ecwid/fake-app/payment')
    {
        return $this->post($url, ['enc_data' => EcwidCrypto::encrypt(self::SECRET, $payload)]);
    }

    public function test_payment_request_redirects_to_gateway(): void
    {
        $this->postPayment($this->ecwidRequest())->assertRedirect('https://pay.example/session/abc');

        $transaction = PaymentTransaction::sole();
        $this->assertSame('transaction_42', $transaction->order_id);
        $this->assertSame('XJ12H', $transaction->order_number);
        $this->assertSame('sess_abc', $transaction->gateway_reference);
        $this->assertSame('secret_token', $transaction->ecwid_token);
        $this->assertSame(PaymentStatus::Pending, $transaction->status);

        $request = FakeGateway::$lastRequest;
        $this->assertSame(12.5, $request->total);
        $this->assertSame('OMR', $request->currency);
        $this->assertCount(1, $request->items);
        $this->assertSame(route('payments.return', $transaction), $request->successUrl);

        $this->assertSame('Active', EcwidStore::sole()->license_status);
        Http::assertNothingSent();
    }

    public function test_paid_return_marks_order_paid_and_goes_back_to_store(): void
    {
        $this->postPayment($this->ecwidRequest());
        $transaction = PaymentTransaction::sole();

        $this->get(route('payments.return', $transaction))
            ->assertRedirect($transaction->return_url);

        Http::assertSent(fn (Request $r) => $r->method() === 'PUT'
            && $r->url() === 'https://app.ecwid.com/api/v3/1003/orders/transaction_42'
            && $r->hasHeader('Authorization', 'Bearer secret_token')
            && $r['paymentStatus'] === 'PAID'
            && $r['externalTransactionId'] === 'sess_abc');

        $transaction->refresh();
        $this->assertSame(PaymentStatus::Paid, $transaction->status);
        $this->assertNotNull($transaction->ecwid_synced_at);

        $log = GatewayLog::where('service', 'ecwid')->sole();
        $this->assertSame('order.update', $log->operation);
        $this->assertSame($transaction->id, $log->payment_transaction_id);
        $this->assertSame('••••oken', $log->request_headers['Authorization']);
        $this->assertStringNotContainsString('secret_token', json_encode($log->getAttributes()));
        $this->assertSame('{"paymentStatus":"PAID","externalTransactionId":"sess_abc"}', $log->request_body);
    }

    public function test_returning_twice_does_not_update_ecwid_twice(): void
    {
        $this->postPayment($this->ecwidRequest());
        $transaction = PaymentTransaction::sole();

        $this->get(route('payments.return', $transaction));
        $this->get(route('payments.return', $transaction))->assertRedirect($transaction->return_url);

        Http::assertSentCount(1);
        $this->assertSame(1, FakeGateway::$statusChecks);
    }

    public function test_cancel_sets_incomplete_and_shows_error(): void
    {
        FakeGateway::$status = PaymentStatus::Pending;
        $this->postPayment($this->ecwidRequest());
        $transaction = PaymentTransaction::sole();

        $response = $this->get(route('payments.cancel', $transaction));

        $this->assertStringStartsWith($transaction->return_url.'&errorMsg=', $response->headers->get('Location'));
        Http::assertSent(fn (Request $r) => $r['paymentStatus'] === 'INCOMPLETE');
        $this->assertSame(PaymentStatus::Cancelled, $transaction->fresh()->status);
    }

    public function test_cancel_url_still_reports_paid_if_gateway_says_paid(): void
    {
        $this->postPayment($this->ecwidRequest());
        $transaction = PaymentTransaction::sole();

        $this->get(route('payments.cancel', $transaction))->assertRedirect($transaction->return_url);
        Http::assertSent(fn (Request $r) => $r['paymentStatus'] === 'PAID');
    }

    public function test_unconfirmed_return_leaves_order_alone(): void
    {
        FakeGateway::$status = PaymentStatus::Pending;
        $this->postPayment($this->ecwidRequest());
        $transaction = PaymentTransaction::sole();

        $response = $this->get(route('payments.return', $transaction));

        $this->assertStringContainsString('errorMsg=', $response->headers->get('Location'));
        Http::assertNothingSent();
        $this->assertSame(PaymentStatus::Pending, $transaction->fresh()->status);
    }

    public function test_reconcile_command_settles_pending_payments(): void
    {
        FakeGateway::$status = PaymentStatus::Pending;
        $this->postPayment($this->ecwidRequest());
        $this->travel(10)->minutes();
        FakeGateway::$status = PaymentStatus::Paid;

        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame(PaymentStatus::Paid, PaymentTransaction::sole()->status);
        Http::assertSent(fn (Request $r) => $r['paymentStatus'] === 'PAID');
    }

    public function test_license_without_this_gateway_sends_shopper_back_with_error(): void
    {
        $this->license('Active', 'name=Thawani;status=Active');

        $response = $this->postPayment($this->ecwidRequest());

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://app.ecwid.com/custompaymentapps/1003?', $location);
        $this->assertStringContainsString(rawurlencode('does not include the Fake Pay gateway'), $location);
        $this->assertSame(0, PaymentTransaction::count());
    }

    public function test_inactive_license_sends_shopper_back_with_error(): void
    {
        $this->license('Suspended');

        $response = $this->postPayment($this->ecwidRequest());

        $this->assertStringContainsString('errorMsg=', $response->headers->get('Location'));
        $this->assertNull(FakeGateway::$lastRequest);
    }

    public function test_undecryptable_request_is_rejected(): void
    {
        $this->post('/ecwid/fake-app/payment', ['enc_data' => 'nonsense'])->assertStatus(400);
        $this->post('/ecwid/unknown-app/payment', ['enc_data' => 'nonsense'])->assertNotFound();
    }

    public function test_disabled_app_is_rejected(): void
    {
        $this->ecwidApp->update(['is_active' => false]);

        $this->postPayment($this->ecwidRequest())->assertNotFound();
    }

    public function test_legacy_v1_url_still_works(): void
    {
        $this->postPayment($this->ecwidRequest(), '/payment-request.php')
            ->assertRedirect('https://pay.example/session/abc');
    }

    public function test_legacy_cbc_data_field_is_accepted(): void
    {
        $this->post('/ecwid/fake-app/payment', ['data' => EcwidCrypto::encrypt(self::SECRET, $this->ecwidRequest(), 'aes-128-cbc')])
            ->assertRedirect('https://pay.example/session/abc');
    }
}
