<?php

namespace App\Models;

use App\Licensing\LicenseResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A store that has used one of our apps. Merchant settings themselves live in
 * Ecwid app storage; this keeps what we need to see in /admin: who the store
 * is and the state of its S4D license.
 */
#[Hidden(['webhook_secret'])]
#[Fillable([
    'ecwid_app_id', 'store_id', 'store_name', 'store_url', 'store_email', 'webhook_secret', 'last_webhook_at',
    'license_key', 'license_status', 'license_description', 'license_gateways', 'license_data',
    'license_checked_at', 'license_verified_at', 'license_checked_via', 'last_seen_at',
])]
class EcwidStore extends Model
{
    protected function casts(): array
    {
        return [
            'license_gateways' => 'array',
            'license_data' => 'array',
            'license_checked_at' => 'datetime',
            'license_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'webhook_secret' => 'encrypted',
            'last_webhook_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EcwidStore $store) {
            $store->webhook_token ??= Str::random(40);
        });
    }

    /** The URL the merchant gives the gateway for webhooks. */
    public function webhookUrl(): string
    {
        if (! $this->webhook_token) {
            // Stores recorded before webhooks existed get a token on first use.
            $this->forceFill(['webhook_token' => Str::random(40)])->save();
        }

        return route('webhooks.receive', $this->webhook_token);
    }

    /**
     * The store's domain (from its Ecwid profile URL, without "www."), sent
     * to WHMCS with license checks. Null until the profile has been read.
     */
    public function domain(): ?string
    {
        $host = parse_url((string) $this->store_url, PHP_URL_HOST);

        return $host ? preg_replace('/^www\./', '', strtolower($host)) : null;
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(EcwidApp::class, 'ecwid_app_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'store_id', 'store_id')->where('ecwid_app_id', $this->ecwid_app_id);
    }

    /** The stored WHMCS response as a LicenseResult, so gateway coverage follows the current config. */
    public function license(): ?LicenseResult
    {
        if (! $this->license_status) {
            return null;
        }

        return LicenseResult::fromWhmcs(($this->license_data ?? []) + ['status' => $this->license_status, 'description' => $this->license_description]);
    }

    /** Whether the license covers the gateway this store's app uses. */
    public function licenseCoversGateway(): bool
    {
        return (bool) $this->license()?->allowsGateway($this->app->gateway);
    }

    public function displayName(): string
    {
        return $this->store_name ?: 'Store '.$this->store_id;
    }
}
