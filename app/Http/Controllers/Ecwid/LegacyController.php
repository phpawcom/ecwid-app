<?php

namespace App\Http\Controllers\Ecwid;

use App\Http\Controllers\Controller;
use App\Models\EcwidApp;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the v1 URLs registered with Ecwid working (payment-request.php,
 * index.html, clientscript.js), so the Ecwid app
 * settings do not have to change on day one. Each URL maps to the EcwidApp
 * whose legacy_path matches ("." for the root app).
 */
class LegacyController extends Controller
{
    public function paymentRequest(Request $request): Response
    {
        return app(PaymentController::class)->request($request, $this->app());
    }

    public function settings(Request $request): Response
    {
        return app(SettingsController::class)->show($request, $this->app());
    }

    public function storefront(): Response
    {
        return app(StorefrontController::class)->script($this->app());
    }

    private function app(): EcwidApp
    {
        return EcwidApp::where('legacy_path', '.')->firstOrFail();
    }
}
