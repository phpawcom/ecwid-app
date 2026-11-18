<?php

namespace App\Payments;

use App\Ecwid\EcwidApi;
use App\Ecwid\PaymentRequest;
use App\Licensing\StoreLicenseService;
use App\Logging\GatewayLogger;
use App\Models\EcwidApp;
use App\Models\PaymentTransaction;
use App\Payments\Data\CheckoutRequest;
use App\Payments\Exceptions\PaymentException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs an Ecwid payment through whichever gateway the Ecwid app uses:
 * Ecwid payment request -> gateway checkout -> shopper returns ->
 * ask the gateway -> update the Ecwid order -> back to the store.
 */
class PaymentService
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly StoreLicenseService $licenses,
        private readonly EcwidApi $ecwid,
        private readonly GatewayLogger $logger,
    ) {}

    /**
     * Start paying for an Ecwid order. Returns the URL to send the shopper to.
     *
     * @throws PaymentException
     */
    public function begin(EcwidApp $app, PaymentRequest $request): string
    {
        $gateway = $this->gateways->get($app->gateway);
        $settings = $request->merchantSettings();

        $license = $this->licenses->check($app, $request->storeId(), (string) ($settings['s4d_license'] ?? ''));
        if ($problem = $this->licenses->problem($license, $gateway->key(), $gateway->name())) {
            throw new PaymentException($problem.' Please contact the store owner.');
        }

        $transaction = PaymentTransaction::create([
            'ecwid_app_id' => $app->id,
            'store_id' => $request->storeId(),
            'gateway' => $gateway->key(),
            'order_id' => $request->orderApiId(),
            'order_number' => $request->orderNumber(),
            'amount' => $request->total(),
            'currency' => $request->currency(),
            'gateway_settings' => $settings,
            'ecwid_token' => $request->token(),
            'return_url' => $request->returnUrl(),
            'status' => PaymentStatus::Pending,
        ]);

        try {
            $session = $this->logger->withContext($this->logContext($transaction, 'checkout.create'), fn () => $gateway->createCheckout(new CheckoutRequest(
                transaction: $transaction,
                total: $request->total(),
                currency: $request->currency(),
                items: $request->items(),
                settings: $settings,
                successUrl: route('payments.return', $transaction),
                cancelUrl: route('payments.cancel', $transaction),
                orderLabel: 'Order '.$request->orderNumber(),
                customerName: $request->customerName(),
                customerEmail: $request->email(),
                customerPhone: $request->customerPhone(),
                hasDiscount: $request->hasDiscount(),
            )));
        } catch (Throwable $e) {
            $transaction->update(['status' => PaymentStatus::Failed, 'last_error' => $e->getMessage()]);

            throw $e instanceof PaymentException ? $e : new PaymentException('The payment gateway is not available right now. Please try again.', previous: $e);
        }

        $transaction->update([
            'gateway_reference' => $session->reference,
            'gateway_amount' => $session->meta['amount'] ?? null,
            'gateway_currency' => $session->meta['currency'] ?? null,
            'meta' => $session->meta,
        ]);

        return $session->redirectUrl;
    }

    /**
     * Ask the gateway how the payment ended and update the Ecwid order.
     * Safe to call more than once (return page, webhook, reconcile job).
     *
     * @param  bool  $shopperCancelled  the shopper came back through the cancel URL
     */
    public function settle(PaymentTransaction $transaction, bool $shopperCancelled = false): PaymentTransaction
    {
        return Cache::lock('payment-transaction:'.$transaction->id, 30)->block(15, function () use ($transaction, $shopperCancelled) {
            $transaction->refresh();

            if ($transaction->status->isFinal() && $transaction->ecwid_synced_at) {
                return $transaction;
            }

            if (! $transaction->status->isFinal()) {
                try {
                    $status = $this->logger->withContext(
                        $this->logContext($transaction, 'payment.status'),
                        fn () => $this->gateways->get($transaction->gateway)->fetchStatus($transaction),
                    );
                } catch (Throwable $e) {
                    Log::warning('Could not fetch payment status', ['transaction' => $transaction->public_id, 'error' => $e->getMessage()]);
                    $status = PaymentStatus::Pending;
                }

                if ($status === PaymentStatus::Pending && $shopperCancelled) {
                    $status = PaymentStatus::Cancelled;
                }

                $transaction->status = $status;
                $transaction->paid_at = $status === PaymentStatus::Paid ? now() : null;
                $transaction->save();
            }

            // Only tell Ecwid once the outcome is known. Failed/cancelled
            // orders go back to INCOMPLETE (Ecwid advises against CANCELLED).
            if ($transaction->status->isFinal()) {
                $this->syncToEcwid($transaction);
            }

            return $transaction;
        });
    }

    /**
     * Where to send the shopper after settle().
     */
    public function shopperRedirectUrl(PaymentTransaction $transaction): string
    {
        return match ($transaction->status) {
            PaymentStatus::Paid => $transaction->return_url,
            PaymentStatus::Cancelled => self::withError($transaction->return_url, 'The payment was cancelled.'),
            PaymentStatus::Pending => self::withError($transaction->return_url, 'We could not confirm your payment yet. If you were charged, please contact the store.'),
            PaymentStatus::Failed => self::withError($transaction->return_url, 'The payment did not go through. Please try again.'),
        };
    }

    /** Ecwid shows errorMsg to the shopper on the checkout step they came from. */
    public static function withError(string $returnUrl, string $message): string
    {
        return $returnUrl.(str_contains($returnUrl, '?') ? '&' : '?').'errorMsg='.rawurlencode($message);
    }

    private function syncToEcwid(PaymentTransaction $transaction): void
    {
        $ecwidStatus = $transaction->status === PaymentStatus::Paid ? EcwidApi::PAID : EcwidApi::INCOMPLETE;

        try {
            $response = $this->logger->withContext($this->logContext($transaction, 'order.update'), fn () => $this->ecwid->updateOrderPaymentStatus(
                $transaction->store_id,
                $transaction->ecwid_token,
                $transaction->order_id,
                $ecwidStatus,
                $transaction->gateway_reference,
            ));
        } catch (Throwable $e) {
            $transaction->update(['last_error' => 'Ecwid update failed: '.$e->getMessage()]);
            Log::error('Ecwid order update failed', ['transaction' => $transaction->public_id, 'error' => $e->getMessage()]);

            return;
        }

        if ($response->successful()) {
            $transaction->update(['ecwid_synced_at' => now(), 'ecwid_payment_status' => $ecwidStatus, 'last_error' => null]);
        } else {
            $transaction->update(['last_error' => 'Ecwid update failed: HTTP '.$response->status().' '.mb_substr($response->body(), 0, 500)]);
            Log::error('Ecwid order update failed', ['transaction' => $transaction->public_id, 'status' => $response->status(), 'body' => $response->body()]);
        }
    }

    /**
     * @return array{ecwid_app_id: int, store_id: int, payment_transaction_id: int, operation: string}
     */
    private function logContext(PaymentTransaction $transaction, string $operation): array
    {
        return [
            'ecwid_app_id' => $transaction->ecwid_app_id,
            'store_id' => $transaction->store_id,
            'payment_transaction_id' => $transaction->id,
            'operation' => $operation,
        ];
    }
}
