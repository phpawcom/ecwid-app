<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An app registered with Ecwid (public app-market app or a store's custom
 * app), each with its own client_id/client_secret and one payment gateway.
 */
#[Fillable(['client_id', 'client_secret', 'gateway', 'name', 'legacy_path', 'is_active'])]
#[Hidden(['client_secret'])]
class EcwidApp extends Model
{
    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'client_id';
    }

    public function stores(): HasMany
    {
        return $this->hasMany(EcwidStore::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }
}
