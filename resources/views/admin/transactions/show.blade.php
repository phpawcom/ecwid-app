@extends('admin.layout')
@section('title', 'Payment '.$transaction->public_id)
@section('content')
    <p><a href="{{ route('admin.logs.index') }}">‹ Logs</a></p>
    <h1>Order {{ $transaction->order_number }} <span class="badge {{ $transaction->status->value === 'paid' ? 'badge--ok' : ($transaction->status->value === 'pending' ? 'badge--plain' : 'badge--bad') }}">{{ $transaction->status->value }}</span></h1>

    <div class="card">
        <dl class="meta">
            <dt>Payment ID</dt><dd>{{ $transaction->public_id }}</dd>
            <dt>Ecwid app</dt><dd>{{ $transaction->app?->name }} ({{ $transaction->app?->client_id }})</dd>
            <dt>Store ID</dt><dd>{{ $transaction->store_id }}</dd>
            <dt>Ecwid order</dt><dd>{{ $transaction->order_id }}</dd>
            <dt>Amount</dt><dd>{{ rtrim(rtrim($transaction->amount, '0'), '.') }} {{ $transaction->currency }}
                @if ($transaction->gateway_amount !== null)
                    <span class="muted">→ {{ $transaction->gateway_amount }} {{ $transaction->gateway_currency }} (gateway minor units)</span>
                @endif
            </dd>
            <dt>Gateway</dt><dd>{{ $transaction->gateway }} · {{ $transaction->gateway_reference ?? '—' }}</dd>
            <dt>Created</dt><dd>{{ $transaction->created_at->format('Y-m-d H:i:s') }}</dd>
            <dt>Paid</dt><dd>{{ $transaction->paid_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>
            <dt>Ecwid updated</dt><dd>{{ $transaction->ecwid_synced_at ? $transaction->ecwid_synced_at->format('Y-m-d H:i:s').' ('.$transaction->ecwid_payment_status.')' : '—' }}</dd>
            @if ($transaction->last_error)
                <dt>Last error</dt><dd style="color: var(--bad)">{{ $transaction->last_error }}</dd>
            @endif
        </dl>
    </div>

    <h2>Gateway calls</h2>
    <div class="card table-wrap" style="padding: 0">
        <table>
            <thead><tr><th>Time</th><th>Service</th><th>Operation</th><th>Result</th><th>Request</th><th>ms</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td><a href="{{ route('admin.logs.show', $log) }}">{{ $log->created_at->format('Y-m-d H:i:s') }}</a></td>
                    <td>{{ $log->service }}</td>
                    <td>{{ $log->operation }}</td>
                    <td>@include('admin.partials.result')</td>
                    <td class="url" title="{{ $log->url }}">{{ $log->method }} {{ $log->url }}</td>
                    <td>{{ $log->duration_ms ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted" style="padding: 24px; text-align: center">No logs for this payment (they may have been deleted by the retention setting).</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
