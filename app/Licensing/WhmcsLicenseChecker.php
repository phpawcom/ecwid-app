<?php

namespace App\Licensing;

use App\Logging\GatewayLogger;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Checks S4D license keys against the WHMCS Licensing Manager addon
 * (modules/servers/licensing/verify.php), the same server v1 used.
 *
 * Instead of v1's file-based "local key", successful results are cached:
 * they are trusted for `local_key_days`, and if WHMCS cannot be reached the
 * cached result keeps working for another `allow_check_fail_days`.
 */
class WhmcsLicenseChecker
{
    public function __construct(
        private readonly Cache $cache,
        private readonly GatewayLogger $logger,
    ) {}

    public function check(string $licenseKey, bool $forceRemote = false): LicenseResult
    {
        $licenseKey = trim($licenseKey);

        if ($licenseKey === '') {
            return LicenseResult::invalid('No license key entered.');
        }

        if (! config('licensing.enabled')) {
            return new LicenseResult('Active', 'Licensing is disabled on this server.');
        }

        $cacheKey = 'license:'.hash('sha256', $licenseKey);
        $cached = $this->cache->get($cacheKey);
        $cachedAt = isset($cached['checked_at']) ? CarbonImmutable::parse($cached['checked_at']) : null;

        if (! $forceRemote && $cachedAt && $cachedAt->addDays(config('licensing.local_key_days'))->isFuture()) {
            return LicenseResult::fromWhmcs($cached['data'], $cachedAt, fromCache: true);
        }

        $remote = $this->remoteCheck($licenseKey);

        if ($remote === null) {
            // WHMCS unreachable: fall back to the last good result while it is
            // still inside the grace window.
            $graceDays = config('licensing.local_key_days') + config('licensing.allow_check_fail_days');
            if ($cachedAt && $cachedAt->addDays($graceDays)->isFuture()) {
                return LicenseResult::fromWhmcs($cached['data'], $cachedAt, fromCache: true);
            }

            return LicenseResult::invalid('Could not reach the license server. Please try again shortly.');
        }

        $result = LicenseResult::fromWhmcs($remote, CarbonImmutable::now());

        if ($result->isActive()) {
            $this->cache->forever($cacheKey, ['data' => $remote, 'checked_at' => $result->checkedAt->toIso8601String()]);
        } else {
            $this->cache->forget($cacheKey);
        }

        return $result;
    }

    public function forget(string $licenseKey): void
    {
        $this->cache->forget('license:'.hash('sha256', trim($licenseKey)));
    }

    /**
     * @return array<string, string>|null the WHMCS response fields, or null when the server could not be reached
     */
    private function remoteCheck(string $licenseKey): ?array
    {
        $checkToken = time().md5(Str::random(32).$licenseKey);
        $url = config('licensing.server_url').config('licensing.verify_path');
        $fields = [
            'licensekey' => $licenseKey,
            'domain' => $this->domain(),
            'ip' => $this->ip(),
            'dir' => $this->directory(),
            'check_token' => $checkToken,
        ];
        $started = hrtime(true);

        try {
            $response = Http::asForm()->timeout(config('licensing.timeout'))->post($url, $fields);
        } catch (ConnectionException $e) {
            $this->logger->record('whmcs', 'POST', $url, [], http_build_query($fields), null, null, false, $e->getMessage(), self::elapsed($started), 'license.verify');
            Log::warning('License server unreachable', ['error' => $e->getMessage()]);

            return null;
        }

        $logResult = function (bool $success, ?string $error) use ($url, $fields, $response, $started) {
            $this->logger->record('whmcs', 'POST', $url, [], http_build_query($fields), $response->status(), $response->body(), $success, $error, self::elapsed($started), 'license.verify');
        };

        if (! $response->successful()) {
            $logResult(false, 'HTTP '.$response->status());
            Log::warning('License server returned an error', ['status' => $response->status()]);

            return null;
        }

        // verify.php answers with flat XML-ish tags: <status>Active</status>...
        preg_match_all('/<(.*?)>([^<]*)<\/\1>/i', $response->body(), $matches);
        $data = array_combine($matches[1], $matches[2]) ?: [];

        if ($data === []) {
            $logResult(false, 'Unexpected response');

            return ['status' => 'Invalid', 'description' => 'Unexpected response from the license server.'];
        }

        $secret = (string) config('licensing.secret');
        if (! empty($data['md5hash']) && ! hash_equals(md5($secret.$checkToken), $data['md5hash'])) {
            $logResult(false, 'md5hash verification failed');

            return ['status' => 'Invalid', 'description' => 'License server response failed verification.'];
        }

        // "success" here means WHMCS answered properly; the license itself may still be Suspended etc.
        $logResult(true, strcasecmp($data['status'] ?? '', 'Active') === 0 ? null : 'License '.($data['status'] ?? 'Invalid'));

        return $data;
    }

    private function domain(): string
    {
        return config('licensing.domain') ?: (string) parse_url((string) config('app.url'), PHP_URL_HOST);
    }

    private function ip(): string
    {
        return config('licensing.ip') ?: (string) request()->server('SERVER_ADDR', '');
    }

    private function directory(): string
    {
        return config('licensing.directory') ?: base_path();
    }

    private static function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1e6);
    }
}
