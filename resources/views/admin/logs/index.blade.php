@extends('admin.layout')
@section('title', 'Logs')
@section('content')
    <h1>Gateway logs</h1>

    <form method="get" class="card filters">
        <div>
            <label for="service">Service</label>
            <select id="service" name="service">
                <option value="">All</option>
                @foreach ($services as $service)
                    <option value="{{ $service }}" @selected(($filters['service'] ?? '') === $service)>{{ $service }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="operation">Operation</label>
            <select id="operation" name="operation">
                <option value="">All</option>
                @foreach ($operations as $operation)
                    <option value="{{ $operation }}" @selected(($filters['operation'] ?? '') === $operation)>{{ $operation }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="result">Result</label>
            <select id="result" name="result">
                <option value="">All</option>
                <option value="ok" @selected(($filters['result'] ?? '') === 'ok')>OK</option>
                <option value="failed" @selected(($filters['result'] ?? '') === 'failed')>Failed</option>
            </select>
        </div>
        <div>
            <label for="store_id">Store ID</label>
            <input id="store_id" name="store_id" inputmode="numeric" value="{{ $filters['store_id'] ?? '' }}">
        </div>
        <div>
            <label for="transaction">Order / payment ref</label>
            <input id="transaction" name="transaction" value="{{ $filters['transaction'] ?? '' }}">
        </div>
        <div>
            <label for="from">From</label>
            <input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
        </div>
        <div>
            <label for="to">To</label>
            <input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
        </div>
        <div class="actions">
            <button class="btn btn--primary" type="submit">Filter</button>
            <a class="btn" href="{{ route('admin.logs.index') }}">Reset</a>
        </div>
    </form>

    <div class="card table-wrap" style="padding: 0">
        <table>
            <thead>
            <tr>
                <th>Time</th>
                <th>Service</th>
                <th>Operation</th>
                <th>Result</th>
                <th>Store</th>
                <th>Order</th>
                <th>Request</th>
                <th>ms</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($logs as $log)
                <tr class="clickable">
                    <td><a href="{{ route('admin.logs.show', $log) }}">{{ $log->created_at->format('Y-m-d H:i:s') }}</a></td>
                    <td>{{ $log->service }}</td>
                    <td>{{ $log->operation }}</td>
                    <td>@include('admin.partials.result')</td>
                    <td>
                        @php($store = $stores[$log->ecwid_app_id.':'.$log->store_id] ?? null)
                        @if ($store)
                            <a href="{{ route('admin.licenses.show', $store) }}">{{ $store->domain() ?? $store->displayName() }}</a>
                            <div class="muted" style="font-size: 12px">#{{ $log->store_id }}</div>
                        @else
                            {{ $log->store_id ?? '—' }}
                        @endif
                    </td>
                    <td>
                        @if ($log->transaction)
                            <a href="{{ route('admin.transactions.show', $log->transaction) }}">{{ $log->transaction->order_number ?: $log->transaction->public_id }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td class="url" title="{{ $log->url }}">{{ $log->method }} {{ $log->url }}</td>
                    <td>{{ $log->duration_ms ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted" style="padding: 24px; text-align: center">No logs match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pagination', ['paginator' => $logs])
@endsection
