<?php

namespace App\Ecwid;

use App\Logging\GatewayLogger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The few Ecwid REST API calls the app needs. Tokens go in the Authorization
 * header (query-string tokens were switched off in March 2025).
 */
class EcwidApi
{
    public const BASE_URL = 'https://app.ecwid.com/api/v3/';

    /** Payment status values accepted by PUT /orders/{id}. */
    public const PAID = 'PAID';

    public const INCOMPLETE = 'INCOMPLETE';

    public function __construct(private readonly GatewayLogger $logger) {}

    /**
     * Logged in Admin → Logs (service "ecwid"), since it completes a payment.
     */
    public function updateOrderPaymentStatus(int $storeId, string $token, string $orderId, string $status, ?string $externalTransactionId = null): Response
    {
        $path = "$storeId/orders/".rawurlencode($orderId);
        $body = array_filter(['paymentStatus' => $status, 'externalTransactionId' => $externalTransactionId]);
        $headers = ['Authorization' => 'Bearer '.$token, 'Content-Type' => 'application/json'];
        $started = hrtime(true);

        try {
            $response = $this->client($token)->put($path, $body);
        } catch (ConnectionException $e) {
            $this->logger->record('ecwid', 'PUT', self::BASE_URL.$path, $headers, json_encode($body), null, null, false, $e->getMessage(), self::elapsed($started), 'order.update');

            throw $e;
        }

        $this->logger->record('ecwid', 'PUT', self::BASE_URL.$path, $headers, json_encode($body), $response->status(), $response->body(),
            $response->successful(), $response->successful() ? null : 'HTTP '.$response->status(), self::elapsed($started), 'order.update');

        return $response;
    }

    private static function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1e6);
    }

    /**
     * Store name, URL and owner email (needs read_store_profile, which payment apps have).
     *
     * @return array{name: ?string, url: ?string, email: ?string}
     */
    public function getStoreSummary(int $storeId, string $token): array
    {
        $profile = $this->client($token)->get("$storeId/profile")->throw()->json();

        return [
            'name' => data_get($profile, 'settings.storeName') ?: data_get($profile, 'account.accountName'),
            'url' => data_get($profile, 'generalInfo.storeUrl'),
            'email' => data_get($profile, 'account.accountEmail') ?: data_get($profile, 'company.email'),
        ];
    }

    /**
     * All app storage keys for the store, as key => value.
     *
     * @return array<string, string>
     */
    public function getStorage(int $storeId, string $token): array
    {
        $response = $this->client($token)->get("$storeId/storage")->throw();

        $values = [];
        foreach ((array) $response->json() as $entry) {
            if (isset($entry['key'])) {
                $values[$entry['key']] = self::storageValue($entry['value'] ?? '');
            }
        }

        return $values;
    }

    public function putStorage(int $storeId, string $token, string $key, string $value): void
    {
        // Ecwid stores the request body as-is: a JSON {"value": ...} body
        // would come back (and reach payment requests) still wrapped.
        $this->client($token)->withBody($value, 'text/plain')->put("$storeId/storage/".rawurlencode($key))->throw();
    }

    /**
     * A stored value as the merchant entered it. Unwraps values an earlier
     * version saved as {"value": "..."}.
     */
    public static function storageValue(mixed $value): string
    {
        if (is_string($value) && str_starts_with($value, '{"value":')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded) && array_keys($decoded) === ['value'] && is_scalar($decoded['value'])) {
                return (string) $decoded['value'];
            }
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function client(string $token): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->retry(2, 500, throw: false);
    }
}
