@extends('admin.layout')
@section('title', 'Log #'.$log->id)
@section('content')
    <p><a href="{{ url()->previous() === url()->current() ? route('admin.logs.index') : url()->previous() }}">‹ Back</a></p>
    <h1>{{ $log->service }} · {{ $log->operation }} @include('admin.partials.result')</h1>

    <div class="card">
        <dl class="meta">
            <dt>Time</dt><dd>{{ $log->created_at->format('Y-m-d H:i:s T') }}</dd>
            <dt>Request</dt><dd>{{ $log->method }} {{ $log->url }}</dd>
            <dt>HTTP status</dt><dd>{{ $log->response_status ?? '—' }}</dd>
            <dt>Duration</dt><dd>{{ $log->duration_ms !== null ? $log->duration_ms.' ms' : '—' }}</dd>
            <dt>Ecwid app</dt><dd>{{ $log->app?->name ?? '—' }}</dd>
            <dt>Store ID</dt><dd>{{ $log->store_id ?? '—' }}</dd>
            <dt>Domain</dt>
            <dd>
                @if ($store)
                    <a href="{{ route('admin.licenses.show', $store) }}">{{ $store->domain() ?? '—' }}</a>
                @else
                    —
                @endif
            </dd>
            <dt>Payment</dt>
            <dd>
                @if ($log->transaction)
                    <a href="{{ route('admin.transactions.show', $log->transaction) }}">Order {{ $log->transaction->order_number }} ({{ $log->transaction->public_id }})</a>
                @else
                    —
                @endif
            </dd>
            @if ($log->error)
                <dt>Error</dt><dd style="color: var(--bad)">{{ $log->error }}</dd>
            @endif
        </dl>
    </div>

    <div class="grid-2">
        <div>
            <h2>Request</h2>
            <pre>@foreach ($log->request_headers ?? [] as $name => $value){{ $name }}: {{ $value }}
@endforeach</pre>
            <pre style="margin-top: 8px">{{ \App\Models\GatewayLog::pretty($log->request_body) ?: '(no body)' }}</pre>
        </div>
        <div>
            <h2>Response</h2>
            <pre>{{ \App\Models\GatewayLog::pretty($log->response_body) ?: '(no body)' }}</pre>
        </div>
    </div>
@endsection
