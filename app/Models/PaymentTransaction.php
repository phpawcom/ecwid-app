<?php

namespace App\Models;

use App\Payments\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'ecwid_app_id', 'store_id', 'gateway', 'order_id', 'order_number', 'amount', 'currency',
    'gateway_amount', 'gateway_currency', 'gateway_reference', 'gateway_settings', 'ecwid_token',
    'return_url', 'status', 'paid_at', 'ecwid_synced_at', 'ecwid_payment_status', 'last_error', 'meta',
])]
#[Hidden(['gateway_settings', 'ecwid_token'])]
class PaymentTransaction extends Model
{
    use HasUlids;

    /** Only public_id is a ULID; the primary key stays an auto-increment id. */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'gateway_settings' => 'encrypted:array',
            'ecwid_token' => 'encrypted',
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'ecwid_synced_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(EcwidApp::class, 'ecwid_app_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(GatewayLog::class);
    }
}
