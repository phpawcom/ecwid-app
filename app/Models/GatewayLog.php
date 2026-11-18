<?php

namespace App\Models;

use App\Logging\LogRetention;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One HTTP call to a payment gateway (or to Ecwid while settling a payment),
 * with its request and response. Deleted after the retention set in
 * Admin → Settings by `php artisan model:prune` (scheduled daily).
 */
#[Fillable([
    'service', 'operation', 'ecwid_app_id', 'store_id', 'payment_transaction_id', 'method', 'url',
    'request_headers', 'request_body', 'response_status', 'response_body', 'success', 'error', 'duration_ms', 'created_at',
])]
class GatewayLog extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'request_headers' => 'array',
            'success' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function prunable(): Builder
    {
        $days = LogRetention::current()->days();

        return $days === null
            ? static::query()->whereRaw('1 = 0')
            : static::query()->where('created_at', '<', now()->subDays($days));
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(EcwidApp::class, 'ecwid_app_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    /** Pretty-printed body when it is JSON, the raw text otherwise. */
    public static function pretty(?string $body): string
    {
        if ($body === null || $body === '') {
            return '';
        }

        $decoded = json_decode($body, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded)
            ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : $body;
    }
}
