<?php

namespace App\Http\Controllers\Ecwid;

use App\Ecwid\EcwidCrypto;
use App\Ecwid\PaymentRequest;
use App\Http\Controllers\Controller;
use App\Models\EcwidApp;
use App\Models\PaymentTransaction;
use App\Payments\Exceptions\PaymentException;
use App\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * Ecwid's paymentUrl: the shopper's browser POSTs the encrypted order here.
     */
    public function request(Request $request, EcwidApp $app): Response
    {
        abort_unless($app->is_active, 404);

        $data = EcwidCrypto::decryptPaymentRequest($app->client_secret, $request->input('enc_data'), $request->input('data'));
        $paymentRequest = new PaymentRequest($data ?? []);

        if (! $paymentRequest->isValid()) {
            Log::warning('Undecodable Ecwid payment request', ['app' => $app->client_id, 'has_enc_data' => $request->filled('enc_data'), 'has_data' => $request->filled('data')]);

            return response()->view('ecwid.error', ['message' => 'This payment request could not be read. Please go back to the store and try again.'], 400);
        }

        try {
            return redirect()->away($this->payments->begin($app, $paymentRequest));
        } catch (PaymentException $e) {
            return redirect()->away(PaymentService::withError($paymentRequest->returnUrl(), $e->getMessage()));
        }
    }

    /** The gateway's success URL. */
    public function return(PaymentTransaction $transaction): RedirectResponse
    {
        $transaction = $this->payments->settle($transaction);

        return redirect()->away($this->payments->shopperRedirectUrl($transaction));
    }

    /** The gateway's cancel URL. */
    public function cancel(PaymentTransaction $transaction): RedirectResponse
    {
        $transaction = $this->payments->settle($transaction, shopperCancelled: true);

        return redirect()->away($this->payments->shopperRedirectUrl($transaction));
    }
}
