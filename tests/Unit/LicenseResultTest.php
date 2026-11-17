<?php

namespace Tests\Unit;

use App\Licensing\LicenseResult;
use Tests\TestCase;

class LicenseResultTest extends TestCase
{
    public function test_it_parses_whmcs_addons(): void
    {
        $addons = LicenseResult::parseAddons('name=Thawani Gateway;nextduedate=0000-00-00;status=Active|name=Other;nextduedate=2000-01-01;status=Active');

        $this->assertSame('Active', $addons['thawani gateway']['status']);
        $this->assertSame('Overdue', $addons['other']['status']);
    }

    public function test_license_without_addons_unlocks_default_gateways_like_v1(): void
    {
        $result = LicenseResult::fromWhmcs(['status' => 'Active']);

        $this->assertTrue($result->allowsGateway('thawani'));
    }

    public function test_v1_credit_cards_addon_unlocks_thawani(): void
    {
        $result = LicenseResult::fromWhmcs(['status' => 'Active', 'addons' => 'name=Credit Cards;nextduedate=0000-00-00;status=Active']);

        $this->assertTrue($result->allowsGateway('thawani'));
    }

    public function test_gateway_needs_its_own_addon_when_the_license_has_addons(): void
    {
        config(['licensing.gateways.newpay' => ['addons' => ['NewPay']]]);

        $thawaniOnly = LicenseResult::fromWhmcs(['status' => 'Active', 'addons' => 'name=Thawani;status=Active']);
        $this->assertTrue($thawaniOnly->allowsGateway('thawani'));
        $this->assertFalse($thawaniOnly->allowsGateway('newpay'));

        $newPayOnly = LicenseResult::fromWhmcs(['status' => 'Active', 'addons' => 'name=NewPay Gateway;status=Active']);
        $this->assertTrue($newPayOnly->allowsGateway('newpay'));
        $this->assertFalse($newPayOnly->allowsGateway('thawani'));

        $suspended = LicenseResult::fromWhmcs(['status' => 'Active', 'addons' => 'name=NewPay;status=Suspended']);
        $this->assertFalse($suspended->allowsGateway('newpay'));
    }

    public function test_product_name_can_unlock_a_gateway(): void
    {
        $result = LicenseResult::fromWhmcs(['status' => 'Active', 'productname' => 'Ecwid Thawani App', 'addons' => 'name=Support;status=Active']);

        $this->assertTrue($result->allowsGateway('thawani'));
    }

    public function test_inactive_license_unlocks_nothing(): void
    {
        $this->assertSame([], LicenseResult::fromWhmcs(['status' => 'Suspended'])->allowedGateways());
    }
}
