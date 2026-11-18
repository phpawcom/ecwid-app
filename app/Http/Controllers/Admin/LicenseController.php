<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Licensing\StoreLicenseService;
use App\Models\EcwidApp;
use App\Models\EcwidStore;
use App\Models\GatewayLog;
use App\Payments\Contracts\HandlesWebhooks;
use App\Payments\GatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Stores and the S4D licenses they entered in the Ecwid app settings.
 */
class LicenseController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive,none'],
            'app' => ['nullable', 'integer'],
        ]);

        $stores = EcwidStore::query()
            ->with('app')
            ->when($filters['q'] ?? null, function ($query, $q) {
                $query->where(fn ($w) => $w
                    ->where('license_key', 'like', "%$q%")
                    ->orWhere('store_name', 'like', "%$q%")
                    ->orWhere('store_url', 'like', "%$q%")
                    ->orWhere('store_email', 'like', "%$q%")
                    ->orWhere('license_data', 'like', "%$q%")
                    ->orWhere('store_id', ctype_digit($q) ? (int) $q : -1));
            })
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->where('license_status', 'Active'))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($q) => $q->whereNotNull('license_status')->where('license_status', '!=', 'Active'))
            ->when(($filters['status'] ?? null) === 'none', fn ($q) => $q->whereNull('license_key'))
            ->when($filters['app'] ?? null, fn ($q, $v) => $q->where('ecwid_app_id', $v))
            ->orderByDesc('license_checked_at')
            ->orderByDesc('last_seen_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.licenses.index', [
            'stores' => $stores,
            'filters' => $filters,
            'apps' => EcwidApp::orderBy('name')->get(['id', 'name']),
            'counts' => [
                'active' => EcwidStore::where('license_status', 'Active')->count(),
                'inactive' => EcwidStore::whereNotNull('license_status')->where('license_status', '!=', 'Active')->count(),
                'none' => EcwidStore::whereNull('license_key')->count(),
            ],
        ]);
    }

    public function show(EcwidStore $store, GatewayManager $gateways): View
    {
        $store->load('app');

        return view('admin.licenses.show', [
            'store' => $store,
            'webhooks' => $gateways->has($store->app->gateway) && $gateways->get($store->app->gateway) instanceof HandlesWebhooks,
            'license' => $store->license(),
            'transactions' => $store->transactions()->latest('id')->limit(20)->get(),
            'checks' => GatewayLog::query()
                ->where('service', 'whmcs')
                ->where('ecwid_app_id', $store->ecwid_app_id)
                ->where('store_id', $store->store_id)
                ->latest('id')->limit(20)->get(),
        ]);
    }

    /** Ask WHMCS again now, skipping the cached result. */
    public function recheck(EcwidStore $store, StoreLicenseService $licenses): RedirectResponse
    {
        if (! $store->license_key) {
            return back()->withErrors(['license' => 'This store has not entered a license key.']);
        }

        $result = $licenses->check($store->app, $store->store_id, $store->license_key, forceRemote: true, via: StoreLicenseService::VIA_ADMIN);

        return redirect()->route('admin.licenses.show', $store)
            ->with('status', 'Checked with WHMCS: '.$result->status.($result->description ? ' — '.$result->description : '').'.');
    }
}
