<?php

namespace App\Payments\Data;

/**
 * One merchant-facing setting rendered on the Ecwid app settings page.
 */
final class SettingField
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type = 'text', // text | password | checkbox | number
        public readonly string $help = '',
        public readonly bool $required = false,
        public readonly string $default = '',
    ) {}
}
