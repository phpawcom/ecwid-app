<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WHMCS Licensing Server
    |--------------------------------------------------------------------------
    |
    | Merchants enter an S4D license key in the app settings. It is checked
    | against the WHMCS "Licensing Manager" module (modules/servers/licensing).
    | The secret must match the "MD5 Hash Verification" value of the WHMCS
    | licensing product. A gateway sold as its own WHMCS product sets its own
    | "secret" below; the others use this one.
    |
    */

    'enabled' => env('LICENSE_ENABLED', true),

    'server_url' => rtrim(env('LICENSE_SERVER_URL', 'http://licensing-server.s4d.om'), '/').'/',

    'verify_path' => 'modules/servers/licensing/verify.php',

    'secret' => env('LICENSE_SECRET'),

    // The domain/IP/directory reported to WHMCS. If the licensing product
    // enforces them, they must match what the old app reported (its
    // SERVER_NAME and the path of legacy/s4d), or licenses become "Invalid".
    'domain' => env('LICENSE_DOMAIN'),
    'ip' => env('LICENSE_IP'),
    'directory' => env('LICENSE_DIRECTORY'),

    // Days a successful check is trusted before asking WHMCS again, and extra
    // days a cached result keeps working while WHMCS cannot be reached.
    'local_key_days' => (int) env('LICENSE_LOCAL_KEY_DAYS', 1),
    'allow_check_fail_days' => (int) env('LICENSE_CHECK_FAIL_DAYS', 3),

    'timeout' => 15,

    /*
    |--------------------------------------------------------------------------
    | Gateway <-> license mapping
    |--------------------------------------------------------------------------
    |
    | Which license unlocks which payment gateway, and optionally the WHMCS
    | product secret used to check licenses for stores on that gateway. A gateway is allowed when
    | the license is Active and either:
    |   - one of the license's WHMCS addons has a name containing any of the
    |     gateway's "addons" strings (case-insensitive) with status Active, or
    |   - the license's WHMCS product name contains any of its "products", or
    |   - the license has no addons at all and the gateway is listed in
    |     "allow_without_addons" (keeps v1 licenses working: in v1 a license
    |     with no addons, or a "Credit Cards" addon, unlocked Thawani).
    |
    */

    'gateways' => [
        'thawani' => [
            'addons' => ['Thawani', 'Credit'],
            'products' => ['Thawani'],
        ],
        'ompay' => [
            'addons' => ['OmPay', 'TruePay'],
            'products' => ['OmPay'], // "Ecwid: OMPAY Gateway"
            'secret' => env('LICENSE_SECRET_OMPAY'),
        ],
    ],

    'allow_without_addons' => ['thawani'],

];
