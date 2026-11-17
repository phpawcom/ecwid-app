<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A store that has used one of our apps. Merchant settings themselves live in
 * Ecwid app storage; this keeps what we need to see (license state, activity).
 */
#[Fillable(['ecwid_app_id', 'store_id', 'license_key', 'license_status', 'license_gateways', 'license_checked_at', 'last_seen_at'])]
class EcwidStore extends Model
{
    protected function casts(): array
    {
        return [
            'license_gateways' => 'array',
            'license_checked_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(EcwidApp::class, 'ecwid_app_id');
    }
}
