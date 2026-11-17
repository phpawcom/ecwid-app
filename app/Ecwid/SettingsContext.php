<?php

namespace App\Ecwid;

use App\Models\EcwidApp;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Who is using the settings page. Built from Ecwid's encrypted `payload`
 * when the page loads, then handed to the page as an encrypted token the
 * page's own requests send back (cookies are unreliable inside the Ecwid
 * admin iframe).
 */
final class SettingsContext
{
    private const LIFETIME = 4 * 3600;

    public function __construct(
        public readonly EcwidApp $app,
        public readonly int $storeId,
        public readonly string $accessToken,
        public readonly string $lang = 'en',
    ) {}

    public static function fromPayload(EcwidApp $app, string $payload): ?self
    {
        $data = EcwidCrypto::decryptCbc($app->client_secret, $payload);

        if (! $data || empty($data['store_id']) || empty($data['access_token'])) {
            return null;
        }

        return new self($app, (int) $data['store_id'], (string) $data['access_token'], (string) ($data['lang'] ?? 'en'));
    }

    public function toToken(): string
    {
        return Crypt::encrypt([
            'app' => $this->app->id,
            'store' => $this->storeId,
            'token' => $this->accessToken,
            'lang' => $this->lang,
            'exp' => time() + self::LIFETIME,
        ]);
    }

    public static function fromToken(EcwidApp $app, ?string $token): ?self
    {
        if (! $token) {
            return null;
        }

        try {
            $data = Crypt::decrypt($token);
        } catch (DecryptException) {
            return null;
        }

        if (($data['app'] ?? null) !== $app->id || ($data['exp'] ?? 0) < time()) {
            return null;
        }

        return new self($app, (int) $data['store'], (string) $data['token'], (string) $data['lang']);
    }
}
