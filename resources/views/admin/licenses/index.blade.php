@extends('admin.layout')
@section('title', 'Licenses')
@section('content')
    <h1>Licenses</h1>
    <p class="muted" style="margin-top: -8px">
        Stores that opened the app settings in Ecwid or took a payment, with the S4D license they entered.
        {{ $counts['active'] }} active · {{ $counts['inactive'] }} not active · {{ $counts['none'] }} without a key.
    </p>

    <form method="get" class="card filters">
        <div style="grid-column: span 2">
            <label for="q">Search</label>
            <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="License key, store, domain, owner…">
        </div>
        <div>
            <label for="status">License</label>
            <select id="status" name="status">
                <option value="">All</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Not active</option>
                <option value="none" @selected(($filters['status'] ?? '') === 'none')>No key</option>
            </select>
        </div>
        <div>
            <label for="app">Ecwid app</label>
            <select id="app" name="app">
                <option value="">All</option>
                @foreach ($apps as $app)
                    <option value="{{ $app->id }}" @selected((int) ($filters['app'] ?? 0) === $app->id)>{{ $app->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="actions">
            <button class="btn btn--primary" type="submit">Filter</button>
            <a class="btn" href="{{ route('admin.licenses.index') }}">Reset</a>
        </div>
    </form>

    <div class="card table-wrap" style="padding: 0">
        <table>
            <thead>
            <tr>
                <th>Store</th>
                <th>App</th>
                <th>License key</th>
                <th>Status</th>
                <th>Registered to</th>
                <th>Gateways</th>
                <th>Next due</th>
                <th>Last checked</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($stores as $store)
                @php($license = $store->license())
                <tr class="clickable">
                    <td>
                        <a href="{{ route('admin.licenses.show', $store) }}">{{ $store->displayName() }}</a>
                        <div class="muted" style="font-size: 12px">{{ $store->domain() ? $store->domain().' · ' : '' }}#{{ $store->store_id }}</div>
                    </td>
                    <td>{{ $store->app->name }}</td>
                    <td><code>{{ $store->license_key ?? '—' }}</code></td>
                    <td>@include('admin.partials.license-status')</td>
                    <td>{{ data_get($store->license_data, 'registeredname') ?: data_get($store->license_data, 'companyname') ?: '—' }}</td>
                    <td>{{ $license ? (implode(', ', $license->allowedGateways()) ?: '—') : '—' }}</td>
                    <td>{{ data_get($store->license_data, 'nextduedate') ?: '—' }}</td>
                    <td>
                        {{ $store->license_checked_at?->diffForHumans() ?? '—' }}
                        @if ($store->license_checked_via)
                            <div class="muted" style="font-size: 12px">from {{ $store->license_checked_via }}</div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted" style="padding: 24px; text-align: center">No stores yet. A store shows up here once its owner opens the app settings in Ecwid.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pagination', ['paginator' => $stores])
@endsection
