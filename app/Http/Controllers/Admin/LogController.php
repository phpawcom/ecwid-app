<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EcwidStore;
use App\Models\GatewayLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'service' => ['nullable', 'string', 'max:30'],
            'operation' => ['nullable', 'string', 'max:60'],
            'store_id' => ['nullable', 'integer'],
            'transaction' => ['nullable', 'string', 'max:40'],
            'result' => ['nullable', 'in:ok,failed'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $logs = GatewayLog::query()
            ->with(['app:id,name', 'transaction:id,public_id,order_number'])
            ->when($filters['service'] ?? null, fn ($q, $v) => $q->where('service', $v))
            ->when($filters['operation'] ?? null, fn ($q, $v) => $q->where('operation', $v))
            ->when($filters['store_id'] ?? null, fn ($q, $v) => $q->where('store_id', $v))
            ->when($filters['transaction'] ?? null, fn ($q, $v) => $q->whereHas('transaction', fn ($t) => $t->where('public_id', $v)->orWhere('order_number', $v)->orWhere('gateway_reference', $v)))
            ->when(($filters['result'] ?? null) === 'ok', fn ($q) => $q->where('success', true))
            ->when(($filters['result'] ?? null) === 'failed', fn ($q) => $q->where('success', false))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', Carbon::parse($v)->endOfDay()))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.logs.index', [
            'logs' => $logs,
            'stores' => $this->storesFor($logs->getCollection()),
            'filters' => $filters,
            'services' => GatewayLog::query()->distinct()->orderBy('service')->pluck('service'),
            'operations' => GatewayLog::query()->distinct()->orderBy('operation')->pluck('operation'),
        ]);
    }

    public function show(GatewayLog $log): View
    {
        $log->load(['app', 'transaction']);

        return view('admin.logs.show', ['log' => $log, 'store' => $this->storesFor(collect([$log]))->first()]);
    }

    /**
     * The stores the logs belong to (for their domains), keyed "{ecwid_app_id}:{store_id}".
     *
     * @param  Collection<int, GatewayLog>  $logs
     * @return Collection<string, EcwidStore>
     */
    private function storesFor(Collection $logs): Collection
    {
        $storeIds = $logs->pluck('store_id')->filter()->unique();

        if ($storeIds->isEmpty()) {
            return collect();
        }

        return EcwidStore::query()
            ->whereIn('store_id', $storeIds)
            ->whereIn('ecwid_app_id', $logs->pluck('ecwid_app_id')->filter()->unique())
            ->get()
            ->keyBy(fn (EcwidStore $store): string => $store->ecwid_app_id.':'.$store->store_id);
    }
}
