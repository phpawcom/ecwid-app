<?php

namespace App\Ecwid;

/**
 * Decrypts what Ecwid sends us. The key is always the first 16 characters of
 * the app's client_secret and data is URL-safe base64 with the IV in the
 * first 16 bytes.
 *
 * - Payment requests: `enc_data` is AES-128-GCM (16-byte tag at the end);
 *   the older `data` field is AES-128-CBC.
 * - Settings page `payload` query parameter: AES-128-CBC.
 *
 * @see https://docs.ecwid.com/guides/payments/process-online-payment-requests/step-1.-decode-and-parse-payment-request-from-ecwid.md
 */
class EcwidCrypto
{
    /**
     * @return array<string, mixed>|null
     */
    public static function decryptGcm(string $clientSecret, string $data): ?array
    {
        $raw = self::decode($data);
        if ($raw === null || strlen($raw) <= 32) {
            return null;
        }

        $json = openssl_decrypt(
            substr($raw, 16, -16), 'aes-128-gcm', self::key($clientSecret), OPENSSL_RAW_DATA,
            substr($raw, 0, 16), substr($raw, -16),
        );

        return self::json($json);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function decryptCbc(string $clientSecret, string $data): ?array
    {
        $raw = self::decode($data);
        if ($raw === null || strlen($raw) <= 16) {
            return null;
        }

        $json = openssl_decrypt(substr($raw, 16), 'aes-128-cbc', self::key($clientSecret), OPENSSL_RAW_DATA, substr($raw, 0, 16));

        return self::json($json);
    }

    /**
     * Decrypt a payment request: prefer `enc_data` (GCM), fall back to `data` (CBC).
     *
     * @return array<string, mixed>|null
     */
    public static function decryptPaymentRequest(string $clientSecret, ?string $encData, ?string $data): ?array
    {
        if ($encData) {
            $decoded = self::decryptGcm($clientSecret, $encData);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        return $data ? self::decryptCbc($clientSecret, $data) : null;
    }

    private static function key(string $clientSecret): string
    {
        return substr($clientSecret, 0, 16);
    }

    private static function decode(string $data): ?string
    {
        $base64 = strtr(trim($data), '-_', '+/');
        $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);
        $raw = base64_decode($base64, true);

        return $raw === false ? null : $raw;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function json(string|false $json): ?array
    {
        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Encrypt like Ecwid does. Only used by tests and local tooling.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function encrypt(string $clientSecret, array $payload, string $cipher = 'aes-128-gcm'): string
    {
        $iv = random_bytes(16);
        $tag = '';
        $json = json_encode($payload);

        $raw = $cipher === 'aes-128-gcm'
            ? openssl_encrypt($json, $cipher, self::key($clientSecret), OPENSSL_RAW_DATA, $iv, $tag, '', 16)
            : openssl_encrypt($json, $cipher, self::key($clientSecret), OPENSSL_RAW_DATA, $iv);

        return rtrim(strtr(base64_encode($iv.$raw.$tag), '+/', '-_'), '=');
    }
}
