<?php

namespace Tests\Feature;

use App\Ecwid\EcwidCrypto;
use App\Licensing\LicenseResult;
use App\Licensing\WhmcsLicenseChecker;
use App\Models\EcwidApp;
use App\Models\EcwidStore;
use App\Models\GatewayLog;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Payments\Gateways\Thawani\ThawaniGateway;
use App\Payments\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class ThawaniWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'abcdefghijklmnop0123456789ABCDEF';

    private const WEBHOOK_SECRET = 'whsec_test_123';

    private EcwidApp $thawani;

    private EcwidStore $store;

    private PaymentTransaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->thawani = EcwidApp::create(['client_id' => 'thawani-gateway', 'client_secret' => self::SECRET, 'gateway' => 'thawani', 'name' => 'Thawani']);
        $this->store = EcwidStore::create(['ecwid_app_id' => $this->thawani->id, 'store_id' => 1003, 'webhook_secret' => self::WEBHOOK_SECRET]);
        $this->transaction = PaymentTransaction::create([
            'ecwid_app_id' => $this->thawani->id, 'store_id' => 1003, 'gateway' => 'thawani', 'order_id' => 'transaction_42',
            'order_number' => 'XJ12H', 'amount' => 1.5, 'currency' => 'OMR', 'ecwid_token' => 'secret_token',
            'return_url' => 'https://app.ecwid.com/custompaymentapps/1003', 'gateway_reference' => 'checkout_abc',
            'gateway_settings' => ['publishable_key' => 'pk', 'secret_key' => 'sk', 'testMode' => 'true'],
        ]);

        Http::fake([
            'uatcheckout.thawani.om/api/v1/checkout/session/checkout_abc' => Http::response(['success' => true, 'data' => ['session_id' => 'checkout_abc', 'payment_status' => 'paid', 'invoice' => '2026111800001']]),
            'app.ecwid.com/*' => Http::response(['updateCount' => 1]),
        ]);
    }

    private function body(string $sessionId = 'checkout_abc'): string
    {
        return json_encode(['event_type' => 'checkout.completed', 'data' => ['session_id' => $sessionId, 'payment_status' => 'paid', 'invoice' => '2026111800001']]);
    }

    private function send(string $body, ?string $secret = self::WEBHOOK_SECRET, ?string $token = null)
    {
        $timestamp = (string) time();
        $signature = $secret === null ? 'bad' : hash_hmac('sha256', $body.'-'.$timestamp, $secret);

        return $this->call('POST', '/webhooks/'.($token ?? $this->store->webhook_token), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_THAWANI_SIGNATURE' => $signature,
            'HTTP_THAWANI_TIMESTAMP' => $timestamp,
        ], $body);
    }

    public function test_store_gets_a_unique_webhook_token(): void
    {
        $other = EcwidStore::create(['ecwid_app_id' => $this->thawani->id, 'store_id' => 2004]);

        $this->assertSame(40, strlen($this->store->webhook_token));
        $this->assertNotSame($this->store->webhook_token, $other->webhook_token);
        $this->assertSame(url('/webhooks/'.$this->store->webhook_token), $this->store->webhookUrl());
    }

    public function test_signed_webhook_marks_the_order_paid(): void
    {
        $this->send($this->body())->assertOk()->assertJson(['message' => 'Payment paid.']);

        $this->transaction->refresh();
        $this->assertSame(PaymentStatus::Paid, $this->transaction->status);
        $this->assertNotNull($this->transaction->ecwid_synced_at);
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r['paymentStatus'] === 'PAID');
        $this->assertNotNull($this->store->fresh()->last_webhook_at);

        $log = GatewayLog::where('operation', 'webhook.received')->sole();
        $this->assertTrue($log->success);
        $this->assertSame($this->transaction->id, $log->payment_transaction_id);
        $this->assertStringNotContainsString($this->store->webhook_token, $log->url);
        $this->assertStringStartsWith('••••', $log->request_headers['thawani-signature']);
    }

    public function test_webhook_after_shopper_returned_does_not_update_ecwid_again(): void
    {
        $this->send($this->body())->assertOk();
        $this->send($this->body())->assertOk();

        Http::assertSentCount(2); // one status check, one Ecwid update
    }

    public function test_bad_signature_is_rejected(): void
    {
        $this->send($this->body(), 'wrong-secret')->assertUnauthorized();

        $this->assertSame(PaymentStatus::Pending, $this->transaction->fresh()->status);
        Http::assertNothingSent();
        $this->assertSame('Invalid webhook signature.', GatewayLog::sole()->error);
    }

    public function test_store_without_secret_is_rejected(): void
    {
        $this->store->update(['webhook_secret' => null]);

        $this->send($this->body())->assertUnauthorized()->assertJson(['message' => 'No webhook secret is saved for this store. Enter it in the app settings in Ecwid.']);
        Http::assertNothingSent();
    }

    public function test_unknown_token_is_not_found(): void
    {
        $this->send($this->body(), token: str_repeat('x', 40))->assertNotFound();
        $this->assertSame(0, GatewayLog::count());
    }

    public function test_other_stores_payment_is_not_touched(): void
    {
        $other = EcwidStore::create(['ecwid_app_id' => $this->thawani->id, 'store_id' => 2004, 'webhook_secret' => self::WEBHOOK_SECRET]);

        $this->send($this->body(), token: $other->webhook_token)->assertOk()->assertJson(['message' => 'Ignored: no payment with this reference.']);
        $this->assertSame(PaymentStatus::Pending, $this->transaction->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_webhook_without_session_is_ignored(): void
    {
        $this->send(json_encode(['event_type' => 'customer.created', 'data' => []]))->assertOk()->assertJson(['message' => 'Ignored: not about a payment.']);
    }

    public function test_signature_over_reencoded_json_is_accepted(): void
    {
        $gateway = app(ThawaniGateway::class);
        $raw = "{\n  \"data\": {\"session_id\": \"checkout_abc\", \"url\": \"https://x.test/a\"}\n}";
        $signature = hash_hmac('sha256', json_encode(json_decode($raw, true)).'-123', 's');

        $this->assertTrue($gateway->verifyWebhook($raw, ['thawani-signature' => $signature, 'thawani-timestamp' => '123'], 's'));
        $this->assertFalse($gateway->verifyWebhook($raw, ['thawani-signature' => $signature, 'thawani-timestamp' => '124'], 's'));
        $this->assertFalse($gateway->verifyWebhook($raw, ['thawani-signature' => $signature], 's'));
        $this->assertFalse($gateway->verifyWebhook($raw, ['thawani-signature' => $signature, 'thawani-timestamp' => '123'], ''));
    }

    public function test_settings_page_shows_webhook_url_and_saves_secret_server_side(): void
    {
        $checker = Mockery::mock(WhmcsLicenseChecker::class);
        $checker->shouldReceive('check')->andReturn(LicenseResult::fromWhmcs(['status' => 'Active']));
        $this->app->instance(WhmcsLicenseChecker::class, $checker);
        $this->store->update(['webhook_secret' => null]);

        $payload = EcwidCrypto::encrypt(self::SECRET, ['store_id' => 1003, 'access_token' => 'secret_admin'], 'aes-128-cbc');
        $html = $this->get('/ecwid/thawani-gateway/settings?payload='.$payload)->assertOk()
            ->assertSee($this->store->webhookUrl())
            ->assertSee('Paste the secret from Thawani')
            ->getContent();
        preg_match('/var context = "([^"]+)"/', $html, $m);
        $headers = ['X-Settings-Context' => stripslashes($m[1])];

        $this->postJson('/ecwid/thawani-gateway/settings', ['settings' => ['s4d_license' => 'K'], 'webhook_secret' => ' whsec_new '], $headers)->assertJson(['ok' => true]);
        $this->assertSame('whsec_new', $this->store->fresh()->webhook_secret);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/storage/webhook'));

        $this->postJson('/ecwid/thawani-gateway/settings', ['settings' => ['s4d_license' => 'K'], 'webhook_secret' => ''], $headers);
        $this->assertSame('whsec_new', $this->store->fresh()->webhook_secret);

        $this->get('/ecwid/thawani-gateway/settings?payload='.$payload)->assertSee('Saved. Leave blank to keep it.')->assertDontSee('whsec_new');

        $this->postJson('/ecwid/thawani-gateway/settings', ['settings' => ['s4d_license' => 'K'], 'clear_webhook_secret' => true], $headers);
        $this->assertNull($this->store->fresh()->webhook_secret);
    }

    public function test_admin_store_page_shows_webhook_state(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get("/admin/licenses/{$this->store->id}")->assertOk()
            ->assertSee($this->store->webhookUrl())->assertSee('Saved')->assertSee('None yet');
    }
}
