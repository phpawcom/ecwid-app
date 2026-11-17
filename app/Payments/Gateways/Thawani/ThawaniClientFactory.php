<?php

namespace App\Payments\Gateways\Thawani;

use S4D\Laravel\Thawani\Services\ThawaniService;

/**
 * Builds a phpawcom/thawani_laravel client for one merchant's keys. The
 * package reads its keys from config/thawani.php by default; every Ecwid
 * store has its own keys, so we overwrite them per request.
 */
class ThawaniClientFactory
{
    public function make(string $publishableKey, string $secretKey, bool $testMode): ThawaniService
    {
        $client = new ThawaniService;
        $client->config = [
            'isTestMode' => $testMode ? 1 : 0,
            'public_key' => $publishableKey,
            'private_key' => $secretKey,
            'webhookSecret' => '',
        ];
        $client->debug = (bool) config('app.debug');

        return $client;
    }
}
