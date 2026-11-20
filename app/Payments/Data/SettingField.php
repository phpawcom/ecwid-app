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
        public readonly string $type = 'text', // text | password | checkbox | number | radio
        public readonly string $help = '',
        public readonly bool $required = false,
        public readonly string $default = '',
        /** radio: value => label */
        public readonly array $options = [],
        /** Fields with the same group are shown together under this heading. */
        public readonly ?string $group = null,
        /** For grouped fields: the radio option value that puts this group in use (highlighted on the page). */
        public readonly ?string $activeWhen = null,
    ) {}
}
