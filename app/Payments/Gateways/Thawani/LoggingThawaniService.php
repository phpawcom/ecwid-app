<?php

namespace App\Payments\Gateways\Thawani;

use App\Logging\GatewayLogger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use S4D\Laravel\Thawani\Services\ThawaniService;

/**
 * The package's Thawani client, with its HTTP layer swapped for Laravel's
 * HTTP client so that every call is logged (Admin → Logs), TLS certificates
 * are verified (the package's own post() turns verification off by
 * default), and tests can fake Thawani with Http::fake().
 *
 * post() returns the same shape as the package's, so all of the package's
 * API methods keep working unchanged.
 */
class LoggingThawaniService extends ThawaniService
{
    public function __construct(private readonly GatewayLogger $logger)
    {
        parent::__construct();
    }

    /**
     * @param  array{url?: string, fields?: array, headers?: array}  $parameters
     * @return array{is_success: int, response: string}
     */
    public function post(array $parameters, $method = 'POST')
    {
        $url = (string) ($parameters['url'] ?? '');
        $fields = is_array($parameters['fields'] ?? null) ? $parameters['fields'] : [];
        $headers = array_change_key_case(array_merge(['Content-Type' => 'application/json'], $parameters['headers'] ?? []), CASE_LOWER);
        $method = strtoupper($method);

        $requestBody = null;
        $options = [];
        $request = Http::withHeaders(array_diff_key($headers, ['content-type' => true]))
            ->accept('application/json')
            ->timeout($this->connectTimeOut)
            ->connectTimeout($this->connectTimeOut);

        if ($method === 'GET') {
            if ($fields !== []) {
                $options['query'] = $fields;
            }
        } elseif ($fields !== []) {
            $requestBody = json_encode($fields, JSON_UNESCAPED_UNICODE);
            $request = $request->withBody($requestBody, 'application/json');
        }

        $started = hrtime(true);

        try {
            $response = $request->send($method, $url, $options);
        } catch (ConnectionException $e) {
            $this->logger->record('thawani', $method, $url, $headers, $requestBody, null, null, false, $e->getMessage(), self::elapsed($started));

            return ['is_success' => 0, 'response' => $e->getMessage()];
        }

        $body = $response->body();
        $json = json_decode($body, true);
        $success = $response->successful() && (! is_array($json) || ! array_key_exists('success', $json) || $json['success']);

        $this->logger->record(
            'thawani', $method, $url.($method === 'GET' && $fields !== [] ? '?'.http_build_query($fields) : ''),
            $headers, $requestBody, $response->status(), $body, $success,
            $success ? null : (is_array($json) ? ($json['description'] ?? null) : null), self::elapsed($started),
        );

        return ['is_success' => 1, 'response' => $body];
    }

    private static function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1e6);
    }
}
