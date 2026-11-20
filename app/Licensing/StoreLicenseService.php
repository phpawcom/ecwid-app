<?php

namespace App\Licensing;

use App\Logging\GatewayLogger;
use App\Models\EcwidApp;
use App\Models\EcwidStore;

/**
 * Checks a store's S4D license for the gateway its Ecwid app uses and keeps
 * the result (with the WHMCS details) on the store record shown in
 * Admin → Licenses.
 */
class StoreLicenseService
{
    /** Where a check came from, shown in the admin. */
    public const VIA_SETTINGS = 'settings';

    public const VIA_PAYMENT = 'payment';

    public const VIA_ADMIN = 'admin';

    public function __construct(
        private readonly WhmcsLicenseChecker $checker,
        private readonly GatewayLogger $logger,
    ) {}

    public function check(EcwidApp $app, int $storeId, string $licenseKey, bool $forceRemote = false, string $via = self::VIA_PAYMENT): LicenseResult
    {
        $licenseKey = trim($licenseKey);

        $store = EcwidStore::firstOrNew(['ecwid_app_id' => $app->id, 'store_id' => $storeId]);

        $result = $this->logger->withContext(
            ['ecwid_app_id' => $app->id, 'store_id' => $storeId, 'operation' => 'license.verify'],
            fn () => $this->checker->check($licenseKey, $forceRemote, $store->domain()),
        );

        $store->last_seen_at = now();

        if ($licenseKey === '') {
            $store->fill([
                'license_key' => null, 'license_status' => null, 'license_description' => null,
                'license_gateways' => null, 'license_data' => null, 'license_checked_at' => now(), 'license_checked_via' => $via,
            ]);
        } else {
            if ($store->license_key !== $licenseKey) {
                // A different key: what we knew about the old one no longer applies.
                $store->license_verified_at = null;
            }

            $store->fill([
                'license_key' => $licenseKey,
                'license_status' => $result->status,
                'license_description' => $result->description ?: null,
                'license_gateways' => $result->allowedGateways(),
                'license_data' => array_diff_key($result->raw, array_flip(['md5hash', 'localkey', 'check_token'])) ?: null,
                'license_checked_at' => $result->checkedAt ?? now(),
                'license_checked_via' => $via,
            ]);

            if ($result->isActive()) {
                $store->license_verified_at = $result->checkedAt ?? now();
            }
        }

        $store->save();

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
