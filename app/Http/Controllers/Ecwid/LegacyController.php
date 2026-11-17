<?php

namespace App\Http\Controllers\Ecwid;

use App\Http\Controllers\Controller;
use App\Models\EcwidApp;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the v1 URLs registered with Ecwid working (payment-request.php,
 * index.html, clientscript.js, stores/{domain}/...), so the Ecwid app
 * settings do not have to change on day one. Each URL maps to the EcwidApp
 * whose legacy_path matches ("." for the root app).
 */
class LegacyController extends Controller
{
    public function paymentRequest(Request $request, ?string $store = null): Response
    {
        return app(PaymentController::class)->request($request, $this->app($store));
    }

    public function settings(Request $request, ?string $store = null): Response
    {
        return app(SettingsController::class)->show($request, $this->app($store));
    }

    public function storefront(): Response
    {
        return app(StorefrontController::class)->script($this->app(null));
    }

    private function app(?string $store): EcwidApp
    {
        return EcwidApp::where('legacy_path', $store === null ? '.' : "stores/$store")->firstOrFail();
    }
}
