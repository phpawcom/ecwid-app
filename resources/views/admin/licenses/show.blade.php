@extends('admin.layout')
@section('title', $store->displayName())
@section('content')
    <p><a href="{{ route('admin.licenses.index') }}">‹ Licenses</a></p>
    <h1>{{ $store->displayName() }} @include('admin.partials.license-status')</h1>

    <div class="grid-2">
        <div class="card">
            <h2 style="margin-top: 0">Store</h2>
            <dl class="meta">
                <dt>Ecwid store ID</dt><dd>{{ $store->store_id }}</dd>
                <dt>Domain</dt><dd>{{ $store->domain() ?? '—' }}</dd>
                <dt>Store URL</dt><dd>@if ($store->store_url)<a href="{{ $store->store_url }}" target="_blank" rel="noopener noreferrer">{{ $store->store_url }}</a>@else — @endif</dd>
                <dt>Owner email</dt><dd>{{ $store->store_email ?? '—' }}</dd>
                <dt>Ecwid app</dt><dd>{{ $store->app->name }} <span class="muted">({{ $store->app->client_id }})</span></dd>
                <dt>Gateway</dt><dd>{{ $store->app->gateway }}</dd>
                <dt>Last seen</dt><dd>{{ $store->last_seen_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                @if ($webhooks)
                    <dt>Webhook URL</dt><dd><code>{{ $store->webhookUrl() }}</code></dd>
                    <dt>Webhook secret</dt><dd>{{ $store->webhook_secret ? 'Saved' : 'Not set' }}</dd>
                    <dt>Last webhook</dt><dd>{{ $store->last_webhook_at?->format('Y-m-d H:i') ?? 'None yet' }}</dd>
                @endif
            </dl>
        </div>

        <div class="card">
            <h2 style="margin-top: 0">S4D license</h2>
            <dl class="meta">
                <dt>License key</dt><dd><code>{{ $store->license_key ?? '—' }}</code></dd>
                <dt>WHMCS status</dt><dd>{{ $store->license_status ?? '—' }}{{ $store->license_description ? ' — '.$store->license_description : '' }}</dd>
                <dt>Covers {{ $store->app->gateway }}</dt><dd>{{ $store->license_key ? ($store->licenseCoversGateway() ? 'Yes' : 'No') : '—' }}</dd>
                <dt>Gateways unlocked</dt><dd>{{ $license ? (implode(', ', $license->allowedGateways()) ?: 'None') : '—' }}</dd>
                <dt>Product</dt><dd>{{ data_get($store->license_data, 'productname') ?: '—' }}</dd>
                <dt>Registered to</dt><dd>{{ collect([data_get($store->license_data, 'registeredname'), data_get($store->license_data, 'companyname')])->filter()->implode(' · ') ?: '—' }}</dd>
                <dt>Client email</dt><dd>{{ data_get($store->license_data, 'email') ?: '—' }}</dd>
                <dt>Registered</dt><dd>{{ data_get($store->license_data, 'regdate') ?: '—' }}</dd>
                <dt>Next due</dt><dd>{{ data_get($store->license_data, 'nextduedate') ?: '—' }} {{ data_get($store->license_data, 'billingcycle') ? '('.data_get($store->license_data, 'billingcycle').')' : '' }}</dd>
                <dt>Last checked</dt><dd>{{ $store->license_checked_at?->format('Y-m-d H:i') ?? '—' }} {{ $store->license_checked_via ? 'from '.$store->license_checked_via : '' }}</dd>
                <dt>Last verified active</dt><dd>{{ $store->license_verified_at?->format('Y-m-d H:i') ?? 'Never' }}</dd>
            </dl>
            @if ($store->license_key)
                <form method="post" action="{{ route('admin.licenses.recheck', $store) }}" style="margin-top: 16px">
                    @csrf
                    <button class="btn" type="submit">Re-check with WHMCS now</button>
                </form>
            @endif
        </div>
    </div>

    @if ($license && $license->addons !== [])
        <h2>License addons</h2>
        <div class="card table-wrap" style="padding: 0">
            <table>
                <thead><tr><th>Addon</th><th>Status</th><th>Next due</th></tr></thead>
                <tbody>
                @foreach ($license->addons as $addon)
                    <tr>
                        <td>{{ $addon['name'] }}</td>
                        <td><span class="badge {{ strcasecmp($addon['status'], 'Active') === 0 ? 'badge--ok' : 'badge--bad' }}">{{ $addon['status'] }}</span></td>
                        <td>{{ $addon['nextduedate'] ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h2>License checks</h2>
    <div class="card table-wrap" style="padding: 0">
        <table>
            <thead><tr><th>Time</th><th>Result</th><th>Note</th><th>ms</th></tr></thead>
            <tbody>
            @forelse ($checks as $log)
                <tr>
                    <td><a href="{{ route('admin.logs.show', $log) }}">{{ $log->created_at->format('Y-m-d H:i:s') }}</a></td>
                    <td>@include('admin.partials.result')</td>
                    <td>{{ $log->error ?? 'Active' }}</td>
                    <td>{{ $log->duration_ms ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted" style="padding: 16px; text-align: center">No calls to WHMCS logged for this store (results may have come from the cache, or the logs were deleted by the retention setting).</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <h2>Recent payments</h2>
    <div class="card table-wrap" style="padding: 0">
        <table>
            <thead><tr><th>Created</th><th>Order</th><th>Amount</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($transactions as $transaction)
                <tr>
                    <td><a href="{{ route('admin.transactions.show', $transaction) }}">{{ $transaction->created_at->format('Y-m-d H:i') }}</a></td>
                    <td>{{ $transaction->order_number }}</td>
                    <td>{{ rtrim(rtrim($transaction->amount, '0'), '.') }} {{ $transaction->currency }}</td>
                    <td>{{ $transaction->status->value }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted" style="padding: 16px; text-align: center">No payments yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
