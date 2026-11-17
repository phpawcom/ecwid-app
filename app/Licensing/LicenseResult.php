<?php

namespace App\Licensing;

use Carbon\CarbonImmutable;

/**
 * The outcome of checking an S4D license against the WHMCS licensing server.
 */
final class LicenseResult
{
    /**
     * @param  array<string, array{name: string, status: string, nextduedate: ?string}>  $addons  keyed by lower-cased addon name
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $status,
        public readonly string $description = '',
        public readonly string $productName = '',
        public readonly array $addons = [],
        public readonly array $raw = [],
        public readonly ?CarbonImmutable $checkedAt = null,
        public readonly bool $fromCache = false,
    ) {}

    public static function invalid(string $description): self
    {
        return new self('Invalid', $description);
    }

    /**
     * @param  array<string, mixed>  $data  WHMCS verify.php response fields
     */
    public static function fromWhmcs(array $data, ?CarbonImmutable $checkedAt = null, bool $fromCache = false): self
    {
        return new self(
            status: (string) ($data['status'] ?? 'Invalid'),
            description: (string) ($data['description'] ?? $data['message'] ?? ''),
            productName: (string) ($data['productname'] ?? ''),
            addons: self::parseAddons((string) ($data['addons'] ?? '')),
            raw: $data,
            checkedAt: $checkedAt,
            fromCache: $fromCache,
        );
    }

    public function isActive(): bool
    {
        return strcasecmp($this->status, 'Active') === 0;
    }

    /**
     * Parse the WHMCS addons string: "name=X;nextduedate=Y;status=Active|name=Z;...".
     *
     * @return array<string, array{name: string, status: string, nextduedate: ?string}>
     */
    public static function parseAddons(string $addons): array
    {
        $parsed = [];

        foreach (array_filter(explode('|', $addons)) as $addon) {
            $fields = [];
            foreach (explode(';', $addon) as $pair) {
                [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
                $fields[trim($key)] = trim($value);
            }

            if (($fields['name'] ?? '') === '') {
                continue;
            }

            $due = $fields['nextduedate'] ?? null;
            $status = $fields['status'] ?? 'Unknown';

            // An addon past its due date no longer counts, even if WHMCS
            // has not suspended it yet. 0000-00-00 means "no due date".
            if ($due && $due !== '0000-00-00' && CarbonImmutable::parse($due)->endOfDay()->isPast()) {
                $status = 'Overdue';
            }

            $parsed[mb_strtolower($fields['name'])] = [
                'name' => $fields['name'],
                'status' => $status,
                'nextduedate' => $due,
            ];
        }

        return $parsed;
    }

    /**
     * Gateway keys (from config/payments.php) this license unlocks.
     *
     * @return list<string>
     */
    public function allowedGateways(): array
    {
        if (! $this->isActive()) {
            return [];
        }

        $allowed = [];

        foreach (config('licensing.gateways', []) as $gateway => $rules) {
            if ($this->matchesRules($rules)) {
                $allowed[] = $gateway;
            }
        }

        if ($this->addons === []) {
            $allowed = [...$allowed, ...config('licensing.allow_without_addons', [])];
        }

        return array_values(array_unique($allowed));
    }

    public function allowsGateway(string $gateway): bool
    {
        return in_array($gateway, $this->allowedGateways(), true);
    }

    /**
     * @param  array{addons?: list<string>, products?: list<string>}  $rules
     */
    private function matchesRules(array $rules): bool
    {
        foreach ($rules['products'] ?? [] as $product) {
            if ($this->productName !== '' && stripos($this->productName, $product) !== false) {
                return true;
            }
        }

        foreach ($this->addons as $addon) {
            if (strcasecmp($addon['status'], 'Active') !== 0) {
                continue;
            }
            foreach ($rules['addons'] ?? [] as $needle) {
                if (stripos($addon['name'], $needle) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'description' => $this->description,
            'product' => $this->productName,
            'addons' => array_values($this->addons),
            'gateways' => $this->allowedGateways(),
        ];
    }
}
