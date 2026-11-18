@if (! $store->license_key)
    <span class="badge badge--plain">No license</span>
@elseif ($store->license_status === 'Active')
    @if ($store->licenseCoversGateway())
        <span class="badge badge--ok">Active</span>
    @else
        <span class="badge badge--bad" title="Active, but the license does not include {{ $store->app->gateway }}">Active · no {{ $store->app->gateway }}</span>
    @endif
@else
    <span class="badge badge--bad">{{ $store->license_status ?? 'Unchecked' }}</span>
@endif
