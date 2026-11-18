<?php

namespace App\Http\Controllers;

use App\Logging\GatewayLogger;
use App\Models\EcwidStore;
use App\Models\PaymentTransaction;
use App\Payments\Contracts\HandlesWebhooks;
use App\Payments\GatewayManager;
use App\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Payment webhooks from gateways. Each store has its own URL
 * (/webhooks/{token}); the store's saved webhook secret proves the call
 * came from the gateway, then the payment is settled exactly as when the
 * shopper returns (status fetched from the gateway, Ecwid order updated).
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly PaymentService $payments,
        private readonly GatewayLogger $logger,
    ) {}

    public function receive(Request $request, string $token): JsonResponse
    {
        $store = EcwidStore::with('app')->where('webhook_token', $token)->first();
        $gateway = $store && $this->gateways->has($store->app->gateway) ? $this->gateways->get($store->app->gateway) : null;

        abort_unless($store && $gateway instanceof HandlesWebhooks, 404);

        $context = ['ecwid_app_id' => $store->ecwid_app_id, 'store_id' => $store->store_id, 'operation' => 'webhook.received'];
        $raw = $request->getContent();
        $headers = array_map(fn ($values) => implode(', ', (array) $values), $request->headers->all());

        $respond = function (int $status, string $message, bool $success, ?PaymentTransaction $transaction = null) use ($store, $request, $token, $raw, $headers, $context) {
            $response = response()->json(['message' => $message], $status);
            $this->logger->withContext($context + ['payment_transaction_id' => $transaction?->id], fn () => $this->logger->record(
                $store->app->gateway, 'POST', str_replace($token, '••••'.substr($token, -4), $request->fullUrl()),
                $headers, $raw, $status, $response->getContent(), $success, $success ? null : $message, null,
            ));

            return $response;
        };

        if (! $store->webhook_secret) {
            return $respond(401, 'No webhook secret is saved for this store. Enter it in the app settings in Ecwid.', false);
        }

        if (! $gateway->verifyWebhook($raw, $headers, $store->webhook_secret)) {
            return $respond(401, 'Invalid webhook signature.', false);
        }

        $store->forceFill(['last_webhook_at' => now()])->save();

        $reference = $gateway->webhookReference((array) json_decode($raw, true));
        if ($reference === null) {
            return $respond(200, 'Ignored: not about a payment.', true);
        }

        $transaction = PaymentTransaction::query()
            ->where('ecwid_app_id', $store->ecwid_app_id)
            ->where('store_id', $store->store_id)
            ->where('gateway', $gateway->key())
            ->where('gateway_reference', $reference)
            ->latest('id')
            ->first();

        if (! $transaction) {
            return $respond(200, 'Ignored: no payment with this reference.', true);
        }

        $transaction = $this->payments->settle($transaction);

        return $respond(200, "Payment {$transaction->status->value}.", true, $transaction);
    }
}
