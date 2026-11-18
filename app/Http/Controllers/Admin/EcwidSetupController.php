<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EcwidApp;
use App\Payments\GatewayManager;
use Illuminate\View\View;

/**
 * What to enter on the Ecwid side for each registered app.
 */
class EcwidSetupController extends Controller
{
    /** Scopes the app needs, and why. */
    public const SCOPES = [
        'add_payment_method' => 'Adds the payment method to the store\'s checkout.',
        'read_orders' => 'Reads orders.',
        'update_orders' => 'Marks orders as paid after the payment (PUT /orders).',
        'read_store_profile' => 'Reads the store name, URL and email shown in Admin → Licenses.',
        'update_store_profile' => 'Saves the merchant\'s settings (keys, license) in Ecwid app storage.',
        'add_to_cp' => 'Shows the settings page inside the Ecwid admin.',
        'customize_storefront' => 'Loads the storefront script that shows the card logos at checkout.',
    ];

    public function index(GatewayManager $gateways): View
    {
        $apps = EcwidApp::query()->withCount('stores')->orderByDesc('is_active')->orderBy('name')->get();

        return view('admin.ecwid-setup', [
            'apps' => $apps->map(fn (EcwidApp $app) => [
                'app' => $app,
                'gateway' => $gateways->has($app->gateway) ? $gateways->get($app->gateway)->name() : $app->gateway.' (not installed)',
                'urls' => [
                    ['Payment URL', 'paymentUrl', route('ecwid.payment', $app), 'Ecwid POSTs the order here when the shopper clicks "Place order".'],
                    ['Settings page URL', 'iframeUrl', route('ecwid.settings', $app), 'Opens inside the Ecwid admin (My Apps → the app) for the merchant\'s keys and license.'],
                    ['Storefront script URL', 'customJsUrl', route('ecwid.storefront', $app), 'Optional. Adds the card logos under the payment option at checkout.'],
                ],
                'legacy' => $app->legacy_path === '.' ? $this->legacyUrls() : [],
            ]),
            'scopes' => self::SCOPES,
            'baseUrl' => url('/'),
            'isHttps' => str_starts_with(url('/'), 'https://'),
            'isLocal' => self::isLocalHost((string) parse_url(url('/'), PHP_URL_HOST)),
        ]);
    }

    private static function isLocalHost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.test') || str_ends_with($host, '.local');
    }

    /**
     * v1 URLs this app still answers on (see LegacyController).
     *
     * @return list<array{0: string, 1: string}>
     */
    private function legacyUrls(): array
    {
        return [
            ['paymentUrl', url('payment-request.php')],
            ['iframeUrl', url('index.html')],
            ['customJsUrl', url('clientscript.js')],
        ];
    }
}
