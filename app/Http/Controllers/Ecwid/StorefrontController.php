<?php

namespace App\Http\Controllers\Ecwid;

use App\Http\Controllers\Controller;
use App\Models\EcwidApp;
use Illuminate\Http\Response;

/**
 * Storefront script (the app's customJsUrl): shows the gateway's card logos
 * under its payment option at checkout.
 */
class StorefrontController extends Controller
{
    public function script(EcwidApp $app): Response
    {
        abort_unless($app->is_active, 404);

        $view = view()->exists("ecwid.storefront.{$app->gateway}") ? "ecwid.storefront.{$app->gateway}" : 'ecwid.storefront.default';

        return response()
            ->view($view, ['app' => $app])
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
