<?php

namespace App\Payments\Data;

final class CredentialCheck
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $message,
    ) {}
}
