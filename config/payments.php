<?php

use App\Payments\Gateways\Thawani\ThawaniGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | Payment gateways
    |--------------------------------------------------------------------------
    |
    | Each gateway is a class implementing App\Payments\Contracts\PaymentGateway.
    | To add a gateway: write the class, register it here, map it to a license
    | addon in config/licensing.php, and register an Ecwid app that uses it
    | (php artisan ecwid:app).
    |
    */

    'gateways' => [
        'thawani' => ThawaniGateway::class,
    ],

];
