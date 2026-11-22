<?php

namespace App\Payments\Gateways\OmPay;

/**
 * One OmPay API response. OmPay answers {"success": true, "data": …} or
 * {"success": false, "error": "CODE", "message": "…", "details": [...]}.
 */
final class OmPayResponse
{
    /**
     * @param  ?int  $status  HTTP status, null when OmPay could not be reached
     * @param  array<string, mixed>  $json
     */
    public function __construct(
        public readonly ?int $status,
        public readonly array $json,
        private readonly ?string $connectionError = null,
    ) {}

    public function ok(): bool
    {
        return $this->status !== null && $this->status >= 200 && $this->status < 300 && ($this->json['success'] ?? false) === true;
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return is_array($this->json['data'] ?? null) ? $this->json['data'] : [];
    }

    /** OmPay's error code, e.g. INVALID_CREDENTIALS. */
    public function error(): ?string
    {
        $error = $this->json['error'] ?? null;

        return is_string($error) && $error !== '' ? $error : null;
    }

    public function errorMessage(): string
    {
        if ($this->status === null) {
            return 'Could not reach OmPay'.($this->connectionError ? ': '.$this->connectionError : '.');
        }

        $message = $this->json['message'] ?? $this->data()['error_message'] ?? null;
        $lines = [is_string($message) && $message !== '' ? $message : ($this->error() ?? 'HTTP '.$this->status)];

        foreach ((array) ($this->json['details'] ?? []) as $detail) {
            if (is_array($detail) && isset($detail['message'])) {
                $lines[] = (isset($detail['field']) ? $detail['field'].': ' : '').$detail['message'];
            }
        }

        return implode("\n", $lines);
    }
}
