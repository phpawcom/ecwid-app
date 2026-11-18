<?php

namespace App\Logging;

use App\Models\GatewayLog;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records gateway HTTP calls in gateway_logs. Callers set the context
 * (which app/store/transaction, what operation) around a gateway call and
 * the HTTP layer records each request with it.
 */
class GatewayLogger
{
    /** Bodies above this are cut so one bad response cannot bloat the table. */
    private const MAX_BODY = 65_535;

    /** @var array<string, mixed> */
    private array $context = [];

    /**
     * @template T
     *
     * @param  array{ecwid_app_id?: ?int, store_id?: ?int, payment_transaction_id?: ?int, operation?: string}  $context
     * @param  Closure(): T  $callback
     * @return T
     */
    public function withContext(array $context, Closure $callback): mixed
    {
        $previous = $this->context;
        $this->context = array_merge($previous, $context);

        try {
            return $callback();
        } finally {
            $this->context = $previous;
        }
    }

    /**
     * @param  array<string, string>  $requestHeaders
     */
    public function record(
        string $service,
        string $method,
        string $url,
        array $requestHeaders,
        ?string $requestBody,
        ?int $responseStatus,
        ?string $responseBody,
        bool $success,
        ?string $error,
        ?int $durationMs,
        ?string $operation = null,
    ): void {
        try {
            GatewayLog::create([
                'service' => $service,
                'operation' => $this->context['operation'] ?? $operation ?? strtolower($method).' '.(parse_url($url, PHP_URL_PATH) ?: $url),
                'ecwid_app_id' => $this->context['ecwid_app_id'] ?? null,
                'store_id' => $this->context['store_id'] ?? null,
                'payment_transaction_id' => $this->context['payment_transaction_id'] ?? null,
                'method' => strtoupper($method),
                'url' => $url,
                'request_headers' => self::maskHeaders($requestHeaders),
                'request_body' => self::cut($requestBody),
                'response_status' => $responseStatus,
                'response_body' => self::cut($responseBody),
                'success' => $success,
                'error' => $error,
                'duration_ms' => $durationMs,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Logging must never break a payment.
            Log::warning('Could not write gateway log', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Hide credentials (API keys, bearer tokens), keeping the last 4 characters to tell keys apart.
     *
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    public static function maskHeaders(array $headers): array
    {
        foreach ($headers as $name => $value) {
            if (preg_match('/key|authorization|token|secret|signature/i', (string) $name)) {
                $value = (string) $value;
                $headers[$name] = strlen($value) > 8 ? '••••'.substr($value, -4) : '••••';
            }
        }

        return $headers;
    }

    private static function cut(?string $body): ?string
    {
        if ($body === null || strlen($body) <= self::MAX_BODY) {
            return $body;
        }

        return mb_strcut($body, 0, self::MAX_BODY).'… [truncated, '.strlen($body).' bytes]';
    }
}
