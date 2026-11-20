<?php

namespace Tests\Feature;

use App\Ecwid\EcwidCrypto;
use App\Licensing\LicenseResult;
use App\Licensing\WhmcsLicenseChecker;
use App\Models\EcwidApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'abcdefghijklmnop0123456789ABCDEF';

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.gateways.fake' => FakeGateway::class, 'licensing.gateways.fake' => ['addons' => ['Fake']]]);
        EcwidApp::create(['client_id' => 'fake-app', 'client_secret' => self::SECRET, 'gateway' => 'fake', 'name' => 'Fake']);
        $this->fakeLicense('Active', 'name=Fake Gateway;status=Active');
    }

    private function fakeLicense(string $status, string $addons = '', ?string $expectKey = null, ?bool $expectForce = null): void
    {
        $checker = Mockery::mock(WhmcsLicenseChecker::class);
        $expectation = $checker->shouldReceive('check');
        if ($expectKey !== null) {
            $expectation->with($expectKey, $expectForce, Mockery::any());
        }
        $expectation->andReturn(LicenseResult::fromWhmcs([
            'status' => $status, 'addons' => $addons, 'productname' => 'Ecwid Payments',
            'registeredname' => 'Ahmed Al Balushi', 'companyname' => 'Example LLC', 'nextduedate' => '2027-01-31',
        ], now()->toImmutable()));
        $this->app->instance(WhmcsLicenseChecker::class, $checker);
    }

    private function payload(): string
    {
        return EcwidCrypto::encrypt(self::SECRET, ['store_id' => 1003, 'access_token' => 'secret_admin', 'lang' => 'en', 'view_mode' => 'PAGE'], 'aes-128-cbc');
    }

    /** Opens the page and returns the context token it hands to its scripts. */
    private function openPage(): string
    {
        $html = $this->get('/ecwid/fake-app/settings?payload='.$this->payload())->assertOk()->getContent();
        preg_match('/var context = "([^"]+)"/', $html, $m);

        return stripslashes($m[1]);
    }

    public function test_page_shows_saved_settings_from_ecwid_storage(): void
    {
        Http::fake(['app.ecwid.com/api/v3/1003/storage' => Http::response([
            ['key' => 'api_key', 'value' => 'saved-key'],
            ['key' => 's4d_license', 'value' => 'ECWID-XYZ'],
        ])]);

        $this->get('/ecwid/fake-app/settings?payload='.$this->payload())
            ->assertOk()
            ->assertSee('value="saved-key"', false)
            ->assertSee('value="ECWID-XYZ"', false)
            ->assertSee('API Key');

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer secret_admin'));
    }

    public function test_page_unwraps_values_saved_as_json(): void
    {
        Http::fake(['app.ecwid.com/api/v3/1003/storage' => Http::response([
            ['key' => 'api_key', 'value' => '{"value":"saved-key"}'],
            ['key' => 's4d_license', 'value' => '{"value":"ECWID-XYZ"}'],
        ])]);

        $this->get('/ecwid/fake-app/settings?payload='.$this->payload())
            ->assertOk()
            ->assertSee('value="saved-key"', false)
            ->assertSee('value="ECWID-XYZ"', false)
            ->assertDontSee('{&quot;value', false);
    }

    public function test_license_is_checked_for_the_store_domain(): void
    {
        Http::fake([
            'app.ecwid.com/api/v3/1003/storage' => Http::response([]),
            'app.ecwid.com/api/v3/1003/profile' => Http::response(['generalInfo' => ['storeUrl' => 'https://www.Keatha.com/shop']]),
        ]);
        $checker = Mockery::mock(WhmcsLicenseChecker::class);
        $checker->shouldReceive('check')->once()->with('ECWID-1', true, 'keatha.com')->andReturn(LicenseResult::fromWhmcs(['status' => 'Active']));
        $this->app->instance(WhmcsLicenseChecker::class, $checker);

        $html = $this->get('/ecwid/fake-app/settings?payload='.$this->payload())
            ->assertOk()
            ->assertSee('id="store-domain" value="keatha.com"', false)
            ->getContent();
        preg_match('/var context = "([^"]+)"/', $html, $m);

        $this->postJson('/ecwid/fake-app/settings/license', ['license' => 'ECWID-1', 'refresh' => true], ['X-Settings-Context' => stripslashes($m[1])])->assertOk();
    }

    public function test_page_needs_a_valid_payload(): void
    {
        $this->get('/ecwid/fake-app/settings?payload=bad')
            ->assertForbidden()
            ->assertSee('EcwidApp.init', false);
    }

    public function test_page_without_payload_reads_the_url_fragment(): void
    {
        Http::fake();

        $this->get('/ecwid/fake-app/settings?lang=en')
            ->assertOk()
            ->assertSee('/ecwid/fake-app/settings/open', false)
            ->assertSee('EcwidApp.getPayload', false)
            ->assertDontSee('var context', false);

        Http::assertNothingSent();
    }

    public function test_fragment_page_opens_settings_once_ecwid_accepts_the_token(): void
    {
        Http::fake(['app.ecwid.com/api/v3/1003/storage' => Http::response([['key' => 'api_key', 'value' => 'saved-key']])]);

        $html = $this->post('/ecwid/fake-app/settings/open', ['store_id' => '1003', 'access_token' => 'secret_admin', 'lang' => 'en'])
            ->assertOk()
            ->assertSee('value="saved-key"', false)
            ->getContent();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://app.ecwid.com/api/v3/1003/storage' && $r->hasHeader('Authorization', 'Bearer secret_admin'));
        $this->assertDatabaseHas('ecwid_stores', ['store_id' => 1003]);

        preg_match('/var context = "([^"]+)"/', $html, $m);
        $this->postJson('/ecwid/fake-app/settings/verify', ['settings' => ['api_key' => 'good']], ['X-Settings-Context' => stripslashes($m[1])])
            ->assertJson(['ok' => true]);
    }

    public function test_fragment_page_rejects_a_token_ecwid_does_not_accept(): void
    {
        Http::fake(['app.ecwid.com/*' => Http::response(['errorMessage' => 'Invalid token'], 403)]);

        $this->post('/ecwid/fake-app/settings/open', ['store_id' => '1003', 'access_token' => 'forged', 'lang' => 'en'])
            ->assertForbidden()
            ->assertDontSee('var context', false);

        $this->assertDatabaseMissing('ecwid_stores', ['store_id' => 1003]);
    }

    public function test_fragment_page_needs_store_and_token(): void
    {
        Http::fake();

        $this->post('/ecwid/fake-app/settings/open', ['store_id' => 'abc', 'access_token' => 'x'])->assertForbidden();
        $this->post('/ecwid/fake-app/settings/open', ['store_id' => '1003'])->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_save_writes_each_setting_to_ecwid_storage(): void
    {
        Http::fake(['app.ecwid.com/*' => Http::response([])]);
        $token = $this->openPage();

        $this->postJson('/ecwid/fake-app/settings', ['settings' => ['api_key' => ' new-key ', 'sandbox' => false, 's4d_license' => 'ECWID-1']], ['X-Settings-Context' => $token])
            ->assertOk()->assertJson(['ok' => true]);

        $puts = collect(Http::recorded())->map(fn ($pair) => $pair[0])->filter(fn (Request $r) => $r->method() === 'PUT')
            ->mapWithKeys(fn (Request $r) => [basename($r->url()) => $r->body()]);

        $this->assertSame(['api_key' => 'new-key', 'sandbox' => 'false', 's4d_license' => 'ECWID-1', 'installed' => 'yes'], $puts->all());
    }

    public function test_actions_need_the_context_token(): void
    {
        $this->postJson('/ecwid/fake-app/settings', ['settings' => []])->assertUnauthorized();
        $this->postJson('/ecwid/fake-app/settings', ['settings' => []], ['X-Settings-Context' => 'forged'])->assertUnauthorized();
    }

    public function test_context_token_is_bound_to_its_app(): void
    {
        Http::fake(['app.ecwid.com/*' => Http::response([])]);
        $token = $this->openPage();
        EcwidApp::create(['client_id' => 'other-app', 'client_secret' => self::SECRET, 'gateway' => 'fake', 'name' => 'Other']);

        $this->postJson('/ecwid/other-app/settings', ['settings' => []], ['X-Settings-Context' => $token])->assertUnauthorized();
    }

    public function test_verify_keys(): void
    {
        Http::fake(['app.ecwid.com/*' => Http::response([])]);
        $token = $this->openPage();

        $this->postJson('/ecwid/fake-app/settings/verify', ['settings' => ['api_key' => 'good']], ['X-Settings-Context' => $token])
            ->assertJson(['ok' => true, 'message' => 'Keys work.']);
        $this->postJson('/ecwid/fake-app/settings/verify', ['settings' => ['api_key' => 'bad']], ['X-Settings-Context' => $token])
            ->assertJson(['ok' => false]);
    }

    public function test_license_check_reports_gateway_coverage(): void
    {
        Http::fake(['app.ecwid.com/*' => Http::response([])]);
        $this->fakeLicense('Active', 'name=Thawani;status=Active', 'ECWID-1', true);
        $token = $this->openPage();

        $this->postJson('/ecwid/fake-app/settings/license', ['license' => 'ECWID-1', 'refresh' => true], ['X-Settings-Context' => $token])
            ->assertJson(['ok' => false, 'message' => 'The S4D license does not include the Fake Pay gateway.', 'license' => ['gateways' => ['thawani']]]);
    }

    public function test_storefront_script(): void
    {
        $this->get('/ecwid/fake-app/storefront.js')->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
    }
}
