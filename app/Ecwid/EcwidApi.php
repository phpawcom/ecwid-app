<?php

namespace App\Ecwid;

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

    public function updateOrderPaymentStatus(int $storeId, string $token, string $orderId, string $status, ?string $externalTransactionId = null): Response
    {
        return $this->client($token)->put("$storeId/orders/".rawurlencode($orderId), array_filter([
            'paymentStatus' => $status,
            'externalTransactionId' => $externalTransactionId,
        ]));
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
                $values[$entry['key']] = (string) ($entry['value'] ?? '');
            }
        }

        return $values;
    }

    public function putStorage(int $storeId, string $token, string $key, string $value): void
    {
        $this->client($token)->put("$storeId/storage/".rawurlencode($key), ['value' => $value])->throw();
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
