<?php

namespace App\Ecwid;

use App\Payments\Data\LineItem;
use Illuminate\Support\Arr;

/**
 * A decrypted Ecwid payment request (what Ecwid POSTs to the app's paymentUrl).
 */
final class PaymentRequest
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(public readonly array $data) {}

    public function storeId(): int
    {
        return (int) ($this->data['storeId'] ?? 0);
    }

    public function token(): string
    {
        return (string) ($this->data['token'] ?? '');
    }

    public function returnUrl(): string
    {
        return (string) ($this->data['returnUrl'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function merchantSettings(): array
    {
        return (array) ($this->data['merchantAppSettings'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function order(): array
    {
        return (array) Arr::get($this->data, 'cart.order', []);
    }

    /** The id the docs say to use with PUT /orders/{id}. */
    public function orderApiId(): string
    {
        $order = $this->order();

        return (string) ($order['referenceTransactionId'] ?? ('transaction_'.($order['orderNumber'] ?? '')));
    }

    public function orderNumber(): string
    {
        $order = $this->order();

        return (string) ($order['id'] ?? $order['orderNumber'] ?? '');
    }

    public function total(): float
    {
        return (float) ($this->order()['total'] ?? 0);
    }

    public function currency(): string
    {
        return strtoupper((string) Arr::get($this->data, 'cart.currency', ''));
    }

    public function email(): string
    {
        return (string) ($this->order()['email'] ?? '');
    }

    public function customerName(): string
    {
        $order = $this->order();

        return (string) (Arr::get($order, 'billingPerson.name') ?: Arr::get($order, 'shippingPerson.name', ''));
    }

    public function customerPhone(): string
    {
        $order = $this->order();

        return (string) (Arr::get($order, 'billingPerson.phone') ?: Arr::get($order, 'shippingPerson.phone', ''));
    }

    public function hasDiscount(): bool
    {
        $order = $this->order();

        return (float) ($order['discount'] ?? 0) > 0
            || (float) ($order['couponDiscount'] ?? 0) > 0
            || (float) ($order['giftCardRedemption'] ?? 0) > 0
            || ! empty(Arr::get($order, 'discountCoupon.discount'));
    }

    /**
     * @return list<LineItem>
     */
    public function items(): array
    {
        return array_values(array_map(
            fn (array $item) => new LineItem(
                (string) ($item['name'] ?? 'Item'),
                (float) ($item['price'] ?? $item['productPrice'] ?? 0),
                (int) ($item['quantity'] ?? 1),
            ),
            array_filter((array) ($this->order()['items'] ?? []), 'is_array'),
        ));
    }

    /** Storefront checkout URL, for sending the shopper back with an error before an order exists. */
    public function storefrontUrl(): string
    {
        return rtrim((string) ($this->order()['refererUrl'] ?? ''), '/');
    }

    public function isValid(): bool
    {
        return $this->storeId() > 0 && $this->token() !== '' && $this->returnUrl() !== '' && $this->order() !== [];
    }
}
