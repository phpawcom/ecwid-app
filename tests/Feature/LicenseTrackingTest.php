<?php

namespace Tests\Feature;

use App\Ecwid\EcwidCrypto;
use App\Licensing\LicenseResult;
use App\Licensing\StoreLicenseService;
use App\Licensing\WhmcsLicenseChecker;
use App\Models\EcwidApp;
use App\Models\EcwidStore;
use App\Models\GatewayLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class LicenseTrackingTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'abcdefghijklmnop0123456789ABCDEF';

    private EcwidApp $thawani;

    private bool $whmcsDown = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['licensing.enabled' => true, 'licensing.secret' => 'test-secret']);
        $this->thawani = EcwidApp::create(['client_id' => 'thawani-gateway', 'client_secret' => self::SECRET, 'gateway' => 'thawani', 'name' => 'Thawani']);
    }

    /** A WHMCS verify.php that answers for any key with the given status. */
    private function fakeWhmcs(string $status = 'Active', string $addons = '', string $product = 'Ecwid Thawani'): void
    {
        Http::fake(['licensing-server.s4d.om/*' => function (Request $request) use ($status, $addons) {
            if ($this->whmcsDown) {
                return Http::response('down', 503);
            }
            $hash = md5('test-secret'.$request['check_token']);

            return Http::response("<status>$status</status><registeredname>Ahmed Al Balushi</registeredname><companyname>Example LLC</companyname>"
                .'<email>owner@example.com</email><productname>$product</productname><regdate>2026-01-31</regdate>'
                ."<nextduedate>2027-01-31</nextduedate><billingcycle>Annually</billingcycle><addons>$addons</addons><md5hash>$hash</md5hash>");
        }]);
    }

    private function openSettings(): string
    {
        $payload = EcwidCrypto::encrypt(self::SECRET, ['store_id' => 1003, 'access_token' => 'secret_admin'], 'aes-128-cbc');
        $html = $this->get('/ecwid/thawani-gateway/settings?payload='.$payload)->assertOk()->getContent();
        preg_match('/var context = "([^"]+)"/', $html, $m);

        return stripslashes($m[1]);
    }

    private function fakeEcwid(): void
    {
        Http::fake([
            'app.ecwid.com/api/v3/1003/profile' => Http::response([
                'generalInfo' => ['storeUrl' => 'https://shop.example.om'],
                'account' => ['accountName' => 'Ahmed', 'accountEmail' => 'owner@shop.example.om'],
                'settings' => ['storeName' => 'Example Shop'],
            ]),
            'app.ecwid.com/*' => Http::response([]),
        ]);
    }

    public function test_opening_settings_records_the_store(): void
    {
        $this->fakeEcwid();

        $this->openSettings();

        $store = EcwidStore::sole();
        $this->assertSame('Example Shop', $store->store_name);
        $this->assertSame('https://shop.example.om', $store->store_url);
        $this->assertSame('owner@shop.example.om', $store->store_email);
        $this->assertNull($store->license_key);
    }

    public function test_verifying_a_license_in_ecwid_stores_whmcs_details(): void
    {
        $this->fakeEcwid();
        $this->fakeWhmcs();
        $token = $this->openSettings();

        $this->postJson('/ecwid/thawani-gateway/settings/license', ['license' => ' ECWID-ABCDEFGHIJKLMNOP ', 'refresh' => true], ['X-Settings-Context' => $token])
            ->assertJson(['ok' => true, 'message' => 'License active for Thawani.']);

        $store = EcwidStore::sole();
        $this->assertSame('ECWID-ABCDEFGHIJKLMNOP', $store->license_key);
        $this->assertSame('Active', $store->license_status);
        $this->assertSame('settings', $store->license_checked_via);
        $this->assertNotNull($store->license_verified_at);
        $this->assertSame(['thawani'], $store->license_gateways);
        $this->assertSame('Ahmed Al Balushi', $store->license_data['registeredname']);
        $this->assertSame('2027-01-31', $store->license_data['nextduedate']);
        $this->assertArrayNotHasKey('md5hash', $store->license_data);
        $this->assertTrue($store->licenseCoversGateway());

        $log = GatewayLog::where('service', 'whmcs')->sole();
        $this->assertSame('license.verify', $log->operation);
        $this->assertSame(1003, $log->store_id);
        $this->assertSame($this->thawani->id, $log->ecwid_app_id);
        $this->assertTrue($log->success);
    }

    public function test_saving_settings_records_the_license(): void
    {
        $this->fakeEcwid();
        $this->fakeWhmcs('Suspended');
        $token = $this->openSettings();

        $this->postJson('/ecwid/thawani-gateway/settings', ['settings' => ['publishable_key' => 'pk', 'secret_key' => 'sk', 's4d_license' => 'ECWID-SUSPENDED']], ['X-Settings-Context' => $token])
            ->assertJson(['ok' => true]);

        $store = EcwidStore::sole();
        $this->assertSame('ECWID-SUSPENDED', $store->license_key);
        $this->assertSame('Suspended', $store->license_status);
        $this->assertNull($store->license_verified_at);
        $this->assertSame('License Suspended', GatewayLog::where('service', 'whmcs')->sole()->error);
    }

    public function test_license_server_down_does_not_break_saving(): void
    {
        $this->fakeEcwid();
        Http::fake(['licensing-server.s4d.om/*' => Http::response('down', 503)]);
        $token = $this->openSettings();

        $this->postJson('/ecwid/thawani-gateway/settings', ['settings' => ['s4d_license' => 'ECWID-X']], ['X-Settings-Context' => $token])
            ->assertJson(['ok' => true]);

        $this->assertSame('Invalid', EcwidStore::sole()->license_status);
        $this->assertFalse(GatewayLog::where('service', 'whmcs')->sole()->success);
    }

    public function test_changing_the_key_forgets_the_old_verification(): void
    {
        $this->fakeWhmcs();
        $service = app(StoreLicenseService::class);

        $service->check($this->thawani, 1003, 'ECWID-OLD');
        $this->assertNotNull(EcwidStore::sole()->license_verified_at);

        $this->whmcsDown = true;
        $service->check($this->thawani, 1003, 'ECWID-NEW');
        $this->assertNull(EcwidStore::sole()->license_verified_at);

        $service->check($this->thawani, 1003, '');
        $store = EcwidStore::sole();
        $this->assertNull($store->license_key);
        $this->assertNull($store->license_status);
    }

    public function test_license_without_this_gateway_is_flagged(): void
    {
        $this->fakeWhmcs('Active', 'name=NewPay Gateway;status=Active', 'Ecwid Payments');
        config(['licensing.gateways.newpay' => ['addons' => ['NewPay']]]);

        app(StoreLicenseService::class)->check($this->thawani, 1003, 'ECWID-NEWPAY');

        $store = EcwidStore::sole();
        $this->assertSame('Active', $store->license_status);
        $this->assertFalse($store->licenseCoversGateway());
    }

    public function test_admin_licenses_list_and_detail(): void
    {
        $this->actingAs(User::factory()->create());
        $this->fakeWhmcs();
        app(StoreLicenseService::class)->check($this->thawani, 1003, 'ECWID-ACTIVE1', via: 'settings');
        EcwidStore::where('store_id', 1003)->update(['store_name' => 'Example Shop', 'store_url' => 'https://www.example-shop.om']);
        EcwidStore::create(['ecwid_app_id' => $this->thawani->id, 'store_id' => 2004, 'store_name' => 'Keyless Shop']);

        $this->get('/admin/licenses')->assertOk()
            ->assertSee('Example Shop')->assertSee('example-shop.om')->assertSee('ECWID-ACTIVE1')->assertSee('Ahmed Al Balushi')->assertSee('2027-01-31')
            ->assertSee('Keyless Shop')->assertSee('No license');

        $this->get('/admin/licenses?status=active')->assertOk()->assertSee('Example Shop')->assertDontSee('Keyless Shop');
        $this->get('/admin/licenses?status=none')->assertOk()->assertSee('Keyless Shop')->assertDontSee('Example Shop');
        $this->get('/admin/licenses?q=Balushi')->assertOk()->assertSee('Example Shop')->assertDontSee('Keyless Shop');
        $this->get('/admin/licenses?q=2004')->assertOk()->assertSee('Keyless Shop')->assertDontSee('Example Shop');

        $store = EcwidStore::where('store_id', 1003)->sole();
        $this->get("/admin/licenses/{$store->id}")->assertOk()
            ->assertSee('Example LLC')->assertSee('example-shop.om')->assertSee('Annually')->assertSee('Re-check with WHMCS now')
            ->assertSee(route('admin.logs.show', GatewayLog::where('service', 'whmcs')->sole()), false);
    }

    public function test_admin_recheck_forces_a_remote_check(): void
    {
        $this->actingAs(User::factory()->create());
        $store = EcwidStore::create(['ecwid_app_id' => $this->thawani->id, 'store_id' => 1003, 'license_key' => 'ECWID-K', 'license_status' => 'Active']);

        $checker = Mockery::mock(WhmcsLicenseChecker::class);
        $checker->shouldReceive('check')->once()->with('ECWID-K', true, Mockery::any(), 'thawani')->andReturn(LicenseResult::fromWhmcs(['status' => 'Suspended', 'description' => 'Overdue invoice']));
        $this->app->instance(WhmcsLicenseChecker::class, $checker);

        $this->post("/admin/licenses/{$store->id}/recheck")
            ->assertRedirect("/admin/licenses/{$store->id}")
            ->assertSessionHas('status', 'Checked with WHMCS: Suspended — Overdue invoice.');

        $store->refresh();
        $this->assertSame('Suspended', $store->license_status);
        $this->assertSame('admin', $store->license_checked_via);
    }

    public function test_licenses_need_login(): void
    {
        $this->get('/admin/licenses')->assertRedirect('/admin/login');
    }
}
