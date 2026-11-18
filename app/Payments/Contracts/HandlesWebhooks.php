<?php

namespace App\Payments\Contracts;

/**
 * A gateway that can notify us about payments (server to server), so an
 * order is updated even when the shopper never comes back to the store.
 *
 * Each store gets its own webhook URL (shown on its Ecwid settings page) and
 * enters the gateway's webhook secret there. A webhook only tells us which
 * payment to look at: we still ask the gateway for the payment's real status.
 */
interface HandlesWebhooks
{
    /** Short instructions shown next to the webhook URL on the settings page. */
    public function webhookInstructions(): string;

    /**
     * Whether the webhook really came from the gateway.
     *
     * @param  array<string, string>  $headers  lower-cased header names
     */
    public function verifyWebhook(string $rawBody, array $headers, string $secret): bool;

    /**
     * The gateway reference (the same one CheckoutSession::$reference holds)
     * of the payment this webhook is about, or null if it is not about one.
     *
     * @param  array<string, mixed>  $payload
     */
    public function webhookReference(array $payload): ?string;
}
