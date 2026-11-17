<?php

namespace App\Payments\Data;

final class CheckoutSession
{
    /**
     * @param  string  $redirectUrl  where to send the shopper to pay
     * @param  string  $reference  the gateway's id for this checkout (used to look the payment up later)
     * @param  array<string, mixed>  $meta  anything else worth keeping on the transaction
     */
    public function __construct(
        public readonly string $redirectUrl,
        public readonly string $reference,
        public readonly array $meta = [],
    ) {}
}
