<?php

namespace App\Payments\Data;

final class LineItem
{
    public function __construct(
        public readonly string $name,
        public readonly float $unitPrice,
        public readonly int $quantity,
    ) {}
}
