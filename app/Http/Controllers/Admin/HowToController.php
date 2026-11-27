<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Payments\GatewayManager;
use Illuminate\View\View;

/**
 * Step-by-step guide for registering an Ecwid app and adding a payment gateway.
 */
class HowToController extends Controller
{
    public function __invoke(GatewayManager $gateways): View
    {
        return view('admin.how-to', [
            'gateways' => collect($gateways->keys())->mapWithKeys(fn (string $key) => [$key => $gateways->get($key)->name()]),
            'basePath' => base_path(),
            'branch' => 'laravel',
        ]);
    }
}
