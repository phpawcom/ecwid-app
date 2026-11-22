<?php

namespace App\Payments\Gateways\OmPay;

use App\Logging\GatewayLogger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Minimal OmPay (TruePay) API client for one merchant's key pair. Every call
 * is recorded in Admin → Logs.
 *
 * Docs: https://developer.truepay.ompay.om/docs
 */
class OmPayClient
{
    public const SANDBOX_URL = 'https://api.sandbox.truepay.ompay.om';

    public const PRODUCTION_URL = 'https://api.truepay.ompay.om';

    private const TIMEOUT = 30;

    public function __construct(
        private readonly GatewayLogger $logger,
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly bool $sandbox,
    ) {}

    public function baseUrl(): string
    {
        return $this->sandbox ? self::SANDBOX_URL : self::PRODUCTION_URL;
    }

    /**
     * Start a bank-hosted payment.
     *
     * @param  array{amount: float, currency: string, return_url: string, reference_number?: string}  $payment
     */
    public function createBankHosted(array $payment): OmPayResponse
    {
        return $this->send('POST', '/api/v1/transactions/bank-hosted', $payment);
    }

    /** The authoritative status of a transaction (server to server). */
    public function inquiry(string $transactionId): OmPayResponse
    {
        return $this->send('POST', '/api/v1/transactions/inquiry', ['transaction_id' => $transactionId]);
    }

    /** @param  array<string, scalar>  $query */
    public function listTransactions(array $query = []): OmPayResponse
    {
        return $this->send('GET', '/api/v1/transactions', $query);
    }

    /**
     * @param  array<string, mixed>  $data  JSON body, or query for GET
     */
    private function send(string $method, string $path, array $data): OmPayResponse
    {
        $url = $this->baseUrl().$path;
        $headers = ['ompay-api-key' => $this->apiKey, 'ompay-api-secret' => $this->apiSecret];
        $request = Http::withHeaders($headers)->acceptJson()->timeout(self::TIMEOUT)->connectTimeout(15);

        $body = null;
        $options = [];
        if ($method === 'GET') {
            if ($data !== []) {
                $options['query'] = $data;
                $url .= '?'.http_build_query($data);
            }
        } else {
            $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
            $request = $request->withBody($body, 'application/json');
            $headers['content-type'] = 'application/json';
        }

        $started = hrtime(true);

        try {
            $response = $request->send($method, $this->baseUrl().$path, $options);
        } catch (ConnectionException $e) {
            $this->logger->record('ompay', $method, $url, $headers, $body, null, null, false, $e->getMessage(), self::elapsed($started));

            return new OmPayResponse(null, [], $e->getMessage());
        }

        $result = new OmPayResponse($response->status(), (array) ($response->json() ?? []));

        $this->logger->record(
            'ompay', $method, $url, $headers, $body, $response->status(), $response->body(),
            $result->ok(), $result->ok() ? null : $result->errorMessage(), self::elapsed($started),
        );

        return $result;
    }

    private static function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1e6);
    }
}
