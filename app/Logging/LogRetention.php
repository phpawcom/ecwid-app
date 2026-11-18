<?php

namespace App\Logging;

use App\Models\Setting;

/**
 * How long gateway request/response logs are kept (Admin → Settings).
 */
enum LogRetention: string
{
    case Days7 = '7';
    case Days30 = '30';
    case Days90 = '90';
    case Months6 = '180';
    case Year1 = '365';
    case Years2 = '730';
    case Never = 'never';

    public const SETTING = 'gateway_logs.retention';

    public static function current(): self
    {
        return self::tryFrom((string) Setting::get(self::SETTING, '')) ?? self::default();
    }

    public static function default(): self
    {
        return self::Days90;
    }

    /** Days to keep, or null to keep forever. */
    public function days(): ?int
    {
        return $this === self::Never ? null : (int) $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Days7 => '7 days',
            self::Days30 => '30 days',
            self::Days90 => '90 days',
            self::Months6 => '6 months',
            self::Year1 => '1 year',
            self::Years2 => '2 years',
            self::Never => 'Never delete',
        };
    }
}
