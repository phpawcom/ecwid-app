<?php

namespace App\Http\Controllers\Ecwid;

use App\Ecwid\EcwidApi;
use App\Ecwid\SettingsContext;
use App\Http\Controllers\Controller;
use App\Licensing\StoreLicenseService;
use App\Logging\GatewayLogger;
use App\Models\EcwidApp;
use App\Models\EcwidStore;
use App\Payments\Contracts\HandlesWebhooks;
use App\Payments\Data\SettingField;
use App\Payments\GatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The app page inside the Ecwid admin (the app's iframeUrl). Merchant
 * settings are kept in Ecwid app storage so they arrive with every payment
 * request as merchantAppSettings.
 */
class SettingsController extends Controller
{
    /** Storage key for the license, shared by all gateways. */
    public const LICENSE_KEY = 's4d_license';

    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly EcwidApi $ecwid,
        private readonly StoreLicenseService $licenses,
        private readonly GatewayLogger $logger,
    ) {}

    public function show(Request $request, EcwidApp $app): Response
    {
        abort_unless($app->is_active, 404);

        // Apps without Ecwid's encrypted payload get the store details in the
        // URL fragment, which never reaches us: hand out a small page that
        // reads it and posts it back to open().
        if (! $request->filled('payload')) {
            return response()->view('ecwid.open', ['app' => $app]);
        }

        $context = SettingsContext::fromPayload($app, (string) $request->query('payload'));

        if (! $context) {
            return $this->errorPage($app, 'Open this page from your Ecwid admin (My Apps).', 403);
        }

        $values = [];
        $loadError = null;
        try {
            $values = $this->ecwid->getStorage($context->storeId, $context->accessToken);
        } catch (Throwable $e) {
            Log::warning('Could not read Ecwid app storage', ['store' => $context->storeId, 'error' => $e->getMessage()]);
            $loadError = 'Your saved settings could not be loaded from Ecwid. Reload the page to try again.';
        }

        return $this->settingsPage($context, $values, $loadError);
    }

    /**
     * The settings page for apps whose store details come from the URL
     * fragment (unencrypted, so the browser could send anything). The token
     * is only trusted once Ecwid accepts it for this store.
     */
    public function open(Request $request, EcwidApp $app): Response
    {
        abort_unless($app->is_active, 404);

        $storeId = filter_var($request->input('store_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $token = (string) $request->input('access_token', '');
        $lang = (string) $request->input('lang', 'en');

        if (! $storeId || $token === '') {
            return $this->errorPage($app, 'Open this page from your Ecwid admin (My Apps).', 403);
        }

        $context = new SettingsContext($app, $storeId, $token, preg_match('/^[a-z]{2}(_[A-Za-z]{2})?$/', $lang) ? $lang : 'en');

        try {
            $values = $this->ecwid->getStorage($context->storeId, $context->accessToken);
        } catch (Throwable $e) {
            Log::warning('Ecwid did not accept the settings page token', ['store' => $storeId, 'error' => $e->getMessage()]);

            return $this->errorPage($app, 'Ecwid did not confirm access to this store. Reload the page from your Ecwid admin to try again.', 403);
        }

        return $this->settingsPage($context, $values, null);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function settingsPage(SettingsContext $context, array $values, ?string $loadError): Response
    {
        $app = $context->app;
        $gateway = $this->gateways->get($app->gateway);
        $store = $this->rememberStore($context);

        // First visit: fill in defaults (v1 did this through the JS SDK).
        foreach ($gateway->settingsFields() as $field) {
            $values[$field->key] ??= $field->default;
        }

        return response()->view('ecwid.settings', [
            'app' => $app,
            'gateway' => $gateway,
            'fields' => $gateway->settingsFields(),
            'values' => $values,
            'licenseKey' => self::LICENSE_KEY,
            'storeDomain' => $store->domain(),
            'contextToken' => $context->toToken(),
            'loadError' => $loadError,
            'webhook' => $gateway instanceof HandlesWebhooks ? [
                'url' => $store->webhookUrl(),
                'instructions' => $gateway->webhookInstructions(),
                'hasSecret' => (bool) $store->webhook_secret,
                'lastReceivedAt' => $store->last_webhook_at,
            ] : null,
        ]);
    }

    private function errorPage(EcwidApp $app, string $message, int $status): Response
    {
        return response()->view('ecwid.error', ['message' => $message, 'ecwidAppId' => $app->client_id], $status);
    }

    public function save(Request $request, EcwidApp $app): JsonResponse
    {
        $context = $this->context($request, $app);
        $gateway = $this->gateways->get($app->gateway);

        $values = [];
        foreach ($gateway->settingsFields() as $field) {
            $values[$field->key] = $this->normalise($field, $request->input("settings.{$field->key}"));
        }
        $values[self::LICENSE_KEY] = trim((string) $request->input('settings.'.self::LICENSE_KEY, ''));
        $values['installed'] = 'yes';

        try {
            foreach ($values as $key => $value) {
                $this->ecwid->putStorage($context->storeId, $context->accessToken, $key, $value);
            }
        } catch (Throwable $e) {
            Log::warning('Could not save Ecwid app storage', ['store' => $context->storeId, 'error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'message' => 'Ecwid did not accept the settings. Please try again.'], 502);
        }

        // The webhook secret stays on our server only (Ecwid storage would
        // send it back in every payment request for no reason). Blank keeps
        // the saved one.
        if ($gateway instanceof HandlesWebhooks) {
            $store = EcwidStore::firstOrCreate(['ecwid_app_id' => $app->id, 'store_id' => $context->storeId]);
            $secret = trim((string) $request->input('webhook_secret', ''));
            if ($request->boolean('clear_webhook_secret')) {
                $store->update(['webhook_secret' => null]);
            } elseif ($secret !== '') {
                $store->update(['webhook_secret' => $secret]);
            }
        }

        // Record the license on the store even if the page's own check did not run.
        try {
            $this->licenses->check($app, $context->storeId, $values[self::LICENSE_KEY], via: StoreLicenseService::VIA_SETTINGS);
        } catch (Throwable $e) {
            Log::warning('License check after saving settings failed', ['store' => $context->storeId, 'error' => $e->getMessage()]);
        }

        return response()->json(['ok' => true, 'message' => 'Settings saved.']);
    }

    public function checkLicense(Request $request, EcwidApp $app): JsonResponse
    {
        $context = $this->context($request, $app);
        $gateway = $this->gateways->get($app->gateway);

        $result = $this->licenses->check($app, $context->storeId, (string) $request->input('license'), forceRemote: $request->boolean('refresh'), via: StoreLicenseService::VIA_SETTINGS);
        $problem = $this->licenses->problem($result, $gateway->key(), $gateway->name());

        return response()->json([
            'ok' => $problem === null,
            'message' => $problem ?? "License active for {$gateway->name()}.",
            'license' => $result->toArray(),
        ]);
    }

    public function verifyGateway(Request $request, EcwidApp $app): JsonResponse
    {
        $context = $this->context($request, $app);
        $gateway = $this->gateways->get($app->gateway);

        $settings = [];
        foreach ($gateway->settingsFields() as $field) {
            $settings[$field->key] = $this->normalise($field, $request->input("settings.{$field->key}"));
        }

        try {
            $check = $this->logger->withContext(
                ['ecwid_app_id' => $app->id, 'store_id' => $context->storeId, 'operation' => 'credentials.verify'],
                fn () => $gateway->verifyCredentials($settings),
            );
        } catch (Throwable $e) {
            Log::warning('Gateway credential check failed', ['gateway' => $gateway->key(), 'error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'message' => "Could not reach {$gateway->name()}. Please try again."]);
        }

        return response()->json(['ok' => $check->ok, 'message' => $check->message]);
    }

    /**
     * Keep the store's name and URL for Admin → Licenses. Best effort: the
     * page works without it.
     */
    private function rememberStore(SettingsContext $context): EcwidStore
    {
        $store = EcwidStore::firstOrNew(['ecwid_app_id' => $context->app->id, 'store_id' => $context->storeId]);
        $store->last_seen_at = now();

        try {
            $summary = $this->ecwid->getStoreSummary($context->storeId, $context->accessToken);
            $store->store_name = $summary['name'] ?: $store->store_name;
            $store->store_url = $summary['url'] ?: $store->store_url;
            $store->store_email = $summary['email'] ?: $store->store_email;
        } catch (Throwable $e) {
            Log::info('Could not read Ecwid store profile', ['store' => $context->storeId, 'error' => $e->getMessage()]);
        }

        $store->save();

        return $store;
    }

    private function context(Request $request, EcwidApp $app): SettingsContext
    {
        $context = SettingsContext::fromToken($app, $request->header('X-Settings-Context'));

        abort_unless($context, 401, 'This page has expired. Reload it from your Ecwid admin.');

        return $context;
    }

    private function normalise(SettingField $field, mixed $value): string
    {
        if ($field->type === 'radio') {
            $value = trim((string) $value);

            return array_key_exists($value, $field->options) ? $value : $field->default;
        }

        if ($field->type === 'checkbox') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        }

        return trim((string) $value);
    }
}
