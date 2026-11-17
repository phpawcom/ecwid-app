<?php

namespace App\Http\Controllers\Ecwid;

use App\Ecwid\EcwidApi;
use App\Ecwid\SettingsContext;
use App\Http\Controllers\Controller;
use App\Licensing\StoreLicenseService;
use App\Models\EcwidApp;
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
    ) {}

    public function show(Request $request, EcwidApp $app): Response
    {
        abort_unless($app->is_active, 404);

        $context = SettingsContext::fromPayload($app, (string) $request->query('payload', ''));
        $gateway = $this->gateways->get($app->gateway);

        if (! $context) {
            return response()->view('ecwid.error', ['message' => 'Open this page from your Ecwid admin (My Apps).'], 403);
        }

        $values = [];
        $loadError = null;
        try {
            $values = $this->ecwid->getStorage($context->storeId, $context->accessToken);
        } catch (Throwable $e) {
            Log::warning('Could not read Ecwid app storage', ['store' => $context->storeId, 'error' => $e->getMessage()]);
            $loadError = 'Your saved settings could not be loaded from Ecwid. Reload the page to try again.';
        }

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
            'contextToken' => $context->toToken(),
            'loadError' => $loadError,
        ]);
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

        return response()->json(['ok' => true, 'message' => 'Settings saved.']);
    }

    public function checkLicense(Request $request, EcwidApp $app): JsonResponse
    {
        $context = $this->context($request, $app);
        $gateway = $this->gateways->get($app->gateway);

        $result = $this->licenses->check($app, $context->storeId, (string) $request->input('license'), forceRemote: $request->boolean('refresh'));
        $problem = $this->licenses->problem($result, $gateway->key(), $gateway->name());

        return response()->json([
            'ok' => $problem === null,
            'message' => $problem ?? "License active for {$gateway->name()}.",
            'license' => $result->toArray(),
        ]);
    }

    public function verifyGateway(Request $request, EcwidApp $app): JsonResponse
    {
        $this->context($request, $app);
        $gateway = $this->gateways->get($app->gateway);

        $settings = [];
        foreach ($gateway->settingsFields() as $field) {
            $settings[$field->key] = $this->normalise($field, $request->input("settings.{$field->key}"));
        }

        try {
            $check = $gateway->verifyCredentials($settings);
        } catch (Throwable $e) {
            Log::warning('Gateway credential check failed', ['gateway' => $gateway->key(), 'error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'message' => "Could not reach {$gateway->name()}. Please try again."]);
        }

        return response()->json(['ok' => $check->ok, 'message' => $check->message]);
    }

    private function context(Request $request, EcwidApp $app): SettingsContext
    {
        $context = SettingsContext::fromToken($app, $request->header('X-Settings-Context'));

        abort_unless($context, 401, 'This page has expired. Reload it from your Ecwid admin.');

        return $context;
    }

    private function normalise(SettingField $field, mixed $value): string
    {
        if ($field->type === 'checkbox') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        }

        return trim((string) $value);
    }
}
