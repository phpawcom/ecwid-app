<?php

namespace App\Licensing;

use App\Models\EcwidApp;
use App\Models\EcwidStore;

/**
 * Checks a store's S4D license for the gateway its Ecwid app uses and keeps
 * the result on the store record.
 */
class StoreLicenseService
{
    public function __construct(private readonly WhmcsLicenseChecker $checker) {}

    public function check(EcwidApp $app, int $storeId, string $licenseKey, bool $forceRemote = false): LicenseResult
    {
        $result = $this->checker->check($licenseKey, $forceRemote);

        EcwidStore::updateOrCreate(
            ['ecwid_app_id' => $app->id, 'store_id' => $storeId],
            [
                'license_key' => trim($licenseKey) ?: null,
                'license_status' => $result->status,
                'license_gateways' => $result->allowedGateways(),
                'license_checked_at' => $result->checkedAt ?? now(),
                'last_seen_at' => now(),
            ],
        );

        return $result;
    }

    /**
     * A message for the merchant/shopper when the license does not cover the gateway, or null when it does.
     */
    public function problem(LicenseResult $result, string $gateway, string $gatewayName): ?string
    {
        if (! $result->isActive()) {
            return trim('The S4D license is not active. '.$result->description);
        }

        if (! $result->allowsGateway($gateway)) {
            return "The S4D license does not include the $gatewayName gateway.";
        }

        return null;
    }
}
