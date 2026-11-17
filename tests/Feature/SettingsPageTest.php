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

    public function test_page_needs_a_valid_payload(): void
    {
        $this->get('/ecwid/fake-app/settings')->assertForbidden();
        $this->get('/ecwid/fake-app/settings?payload=bad')->assertForbidden();
    }

    public function test_save_writes_each_setting_to_ecwid_storage(): void
    {
        Http::fake(['app.ecwid.com/*' => Http::response([])]);
        $token = $this->openPage();

        $this->postJson('/ecwid/fake-app/settings', ['settings' => ['api_key' => ' new-key ', 'sandbox' => false, 's4d_license' => 'ECWID-1']], ['X-Settings-Context' => $token])
            ->assertOk()->assertJson(['ok' => true]);

        $puts = collect(Http::recorded())->map(fn ($pair) => $pair[0])->filter(fn (Request $r) => $r->method() === 'PUT')
            ->mapWithKeys(fn (Request $r) => [basename($r->url()) => $r['value']]);

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
        $checker = Mockery::mock(WhmcsLicenseChecker::class);
        $checker->shouldReceive('check')->with('ECWID-1', true)->andReturn(LicenseResult::fromWhmcs(['status' => 'Active', 'addons' => 'name=Thawani;status=Active']));
        $this->app->instance(WhmcsLicenseChecker::class, $checker);
        $token = $this->openPage();

        $this->postJson('/ecwid/fake-app/settings/license', ['license' => 'ECWID-1', 'refresh' => true], ['X-Settings-Context' => $token])
            ->assertJson(['ok' => false, 'message' => 'The S4D license does not include the Fake Pay gateway.', 'license' => ['gateways' => ['thawani']]]);
    }

    public function test_storefront_script(): void
    {
        $this->get('/ecwid/fake-app/storefront.js')->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
    }
}
