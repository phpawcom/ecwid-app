<?php

namespace Tests\Feature;

use App\Licensing\WhmcsLicenseChecker;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhmcsLicenseCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['licensing.enabled' => true, 'licensing.secret' => 'test-secret', 'licensing.local_key_days' => 1, 'licensing.allow_check_fail_days' => 3]);
    }

    private function fakeWhmcs(string $status = 'Active', bool $validHash = true, string $extra = ''): void
    {
        Http::fake(['*/modules/servers/licensing/verify.php' => function (Request $request) use ($status, $validHash, $extra) {
            $hash = $validHash ? md5('test-secret'.$request['check_token']) : 'nope';

            return Http::response("<status>$status</status><productname>Ecwid Thawani</productname><md5hash>$hash</md5hash>$extra");
        }]);
    }

    public function test_active_license(): void
    {
        $this->fakeWhmcs();

        $result = app(WhmcsLicenseChecker::class)->check('ECWID-1234');

        $this->assertTrue($result->isActive());
        $this->assertSame('Ecwid Thawani', $result->productName);
        Http::assertSent(fn (Request $r) => $r['licensekey'] === 'ECWID-1234' && isset($r['domain'], $r['ip'], $r['dir']));
    }

    public function test_reports_the_given_store_domain_and_caches_per_domain(): void
    {
        $this->fakeWhmcs();
        config(['app.url' => 'https://thawani.apps.s4d.om', 'licensing.domain' => null]);
        $checker = app(WhmcsLicenseChecker::class);

        $checker->check('ECWID-1234', domain: 'keatha.com');
        $checker->check('ECWID-1234', domain: 'keatha.com');
        $checker->check('ECWID-1234');

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r['domain'] === 'keatha.com');
        Http::assertSent(fn (Request $r) => $r['domain'] === 'thawani.apps.s4d.om');
    }

    public function test_response_with_wrong_hash_is_rejected(): void
    {
        $this->fakeWhmcs(validHash: false);

        $result = app(WhmcsLicenseChecker::class)->check('ECWID-1234');

        $this->assertFalse($result->isActive());
        $this->assertStringContainsString('verification', $result->description);
    }

    public function test_active_result_is_cached(): void
    {
        $this->fakeWhmcs();
        $checker = app(WhmcsLicenseChecker::class);

        $checker->check('ECWID-1234');
        $second = $checker->check('ECWID-1234');

        $this->assertTrue($second->fromCache);
        Http::assertSentCount(1);
    }

    public function test_cached_result_is_used_while_server_is_down_within_grace(): void
    {
        $down = false;
        Http::fake(['*' => function (Request $request) use (&$down) {
            return $down
                ? Http::response('down', 503)
                : Http::response('<status>Active</status><md5hash>'.md5('test-secret'.$request['check_token']).'</md5hash>');
        }]);
        $checker = app(WhmcsLicenseChecker::class);
        $this->assertTrue($checker->check('ECWID-1234')->isActive());

        $down = true;
        $this->travel(2)->days();
        $this->assertTrue($checker->check('ECWID-1234')->isActive());

        $this->travel(5)->days();
        $this->assertFalse($checker->check('ECWID-1234')->isActive());
    }

    public function test_suspended_license_is_not_cached(): void
    {
        $this->fakeWhmcs('Suspended');
        $checker = app(WhmcsLicenseChecker::class);

        $this->assertFalse($checker->check('ECWID-1234')->isActive());
        $checker->check('ECWID-1234');
        Http::assertSentCount(2);
    }

    public function test_empty_key_is_invalid_without_calling_server(): void
    {
        Http::fake();

        $this->assertFalse(app(WhmcsLicenseChecker::class)->check('  ')->isActive());
        Http::assertNothingSent();
    }
}
