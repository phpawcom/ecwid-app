<?php

namespace App\Payments;

use App\Payments\Contracts\PaymentGateway;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class GatewayManager
{
    /** @var array<string, PaymentGateway> */
    private array $resolved = [];

    public function __construct(private readonly Container $container) {}

    public function get(string $key): PaymentGateway
    {
        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        $class = config("payments.gateways.$key");

        if (! $class) {
            throw new InvalidArgumentException("Unknown payment gateway [$key].");
        }

        return $this->resolved[$key] = $this->container->make($class);
    }

    public function has(string $key): bool
    {
        return (bool) config("payments.gateways.$key");
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys(config('payments.gateways', []));
    }
}
