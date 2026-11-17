<?php

namespace App\Payments\Contracts;

use App\Models\PaymentTransaction;
use App\Payments\Data\CheckoutRequest;
use App\Payments\Data\CheckoutSession;
use App\Payments\Data\CredentialCheck;
use App\Payments\Data\SettingField;
use App\Payments\Exceptions\PaymentException;
use App\Payments\PaymentStatus;

/**
 * A hosted-checkout payment gateway (redirect the customer away, then they
 * come back and we ask the gateway whether the payment went through).
 */
interface PaymentGateway
{
    /** Stable key used in config, the database and license mapping, e.g. "thawani". */
    public function key(): string;

    /** Human readable name, e.g. "Thawani". */
    public function name(): string;

    /**
     * Fields the merchant fills in on the app settings page. They are saved in
     * Ecwid app storage and come back in every payment request as
     * merchantAppSettings, keyed by SettingField::$key.
     *
     * @return list<SettingField>
     */
    public function settingsFields(): array;

    /**
     * Start a checkout. The returned session tells us where to send the
     * customer and how to look the payment up later.
     *
     * @throws PaymentException with a message safe to show the customer
     */
    public function createCheckout(CheckoutRequest $request): CheckoutSession;

    /** Ask the gateway for the current state of a transaction's payment. */
    public function fetchStatus(PaymentTransaction $transaction): PaymentStatus;

    /**
     * Check that the merchant's credentials work (the "Verify" button).
     *
     * @param  array<string, mixed>  $settings
     */
    public function verifyCredentials(array $settings): CredentialCheck;
}
