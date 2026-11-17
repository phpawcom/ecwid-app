<?php

namespace App\Payments\Data;

use App\Models\PaymentTransaction;

/**
 * Everything a gateway needs to start a checkout, independent of Ecwid.
 */
final class CheckoutRequest
{
    /**
     * @param  list<LineItem>  $items
     * @param  array<string, mixed>  $settings  the merchant's settings for this gateway
     */
    public function __construct(
        public readonly PaymentTransaction $transaction,
        public readonly float $total,
        public readonly string $currency,
        public readonly array $items,
        public readonly array $settings,
        public readonly string $successUrl,
        public readonly string $cancelUrl,
        public readonly string $orderLabel,
        public readonly string $customerName = '',
        public readonly string $customerEmail = '',
        public readonly string $customerPhone = '',
        public readonly bool $hasDiscount = false,
    ) {}
}
