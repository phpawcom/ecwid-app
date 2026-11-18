<?php

namespace App\Console\Commands;

use App\Models\EcwidApp;
use App\Payments\GatewayManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ecwid:app
    {client_id : Ecwid app client_id (namespace), e.g. thawani-gateway or custom-app-123-1}
    {--secret= : Ecwid client_secret}
    {--gateway= : Payment gateway key from config/payments.php}
    {--name= : Display name}
    {--legacy-path= : Set to "." for the app that answers on the v1 URLs (payment-request.php, index.html)}
    {--disable : Stop accepting requests for this app}
    {--enable : Accept requests for this app again}')]
#[Description('Register or update an Ecwid app and the payment gateway it uses')]
class EcwidAppCommand extends Command
{
    public function handle(GatewayManager $gateways): int
    {
        $app = EcwidApp::firstOrNew(['client_id' => $this->argument('client_id')]);

        $gateway = $this->option('gateway') ?? $app->gateway;
        if (! $gateway || ! $gateways->has($gateway)) {
            $gateway = $this->choice('Payment gateway', $gateways->keys(), 0);
        }

        $secret = $this->option('secret') ?? ($app->exists ? null : $this->secret('Ecwid client_secret'));

        $app->gateway = $gateway;
        $app->name = $this->option('name') ?? $app->name ?? ucfirst($gateway).' ('.$app->client_id.')';
        if ($secret) {
            $app->client_secret = $secret;
        }
        if ($this->option('legacy-path') !== null) {
            $app->legacy_path = $this->option('legacy-path') ?: null;
        }
        if ($this->option('disable')) {
            $app->is_active = false;
        } elseif ($this->option('enable') || ! $app->exists) {
            $app->is_active = true;
        }

        if (! $app->client_secret) {
            $this->error('A client_secret is required.');

            return self::FAILURE;
        }

        $app->save();

        $this->components->info(($app->wasRecentlyCreated ? 'Registered' : 'Updated')." {$app->name}.");
        $this->table(['Ecwid setting', 'URL'], [
            ['paymentUrl', route('ecwid.payment', $app)],
            ['iframeUrl', route('ecwid.settings', $app)],
            ['customJsUrl', route('ecwid.storefront', $app)],
        ]);

        return self::SUCCESS;
    }
}
