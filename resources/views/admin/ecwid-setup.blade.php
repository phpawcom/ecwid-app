@extends('admin.layout')
@section('title', 'Ecwid setup')
@section('content')
    <h1>Ecwid setup</h1>
    <p class="muted" style="margin-top: -8px; max-width: 760px">
        Each Ecwid app (the public Thawani app or a store's custom app) needs these URLs in its settings on the Ecwid side.
        Set them in the app's details in your Ecwid developer account, or send them to Ecwid support for apps they manage.
        The client secret Ecwid gave you for the app must match the one registered here.
    </p>

    @if (! $isHttps || $isLocal)
        <div class="errors" style="max-width: 760px">
            These URLs start with <strong>{{ $baseUrl }}</strong>, the address you opened this admin on.
            Ecwid needs a public <strong>https</strong> address, so copy them from the admin on the live server (with <code>APP_URL</code> set to its https URL).
        </div>
    @endif

    @forelse ($apps as $entry)
        @php($app = $entry['app'])
        <div class="card">
            <h2 style="margin-top: 0; display: flex; gap: 8px; align-items: center; flex-wrap: wrap">
                {{ $app->name }}
                <span class="badge badge--plain">{{ $entry['gateway'] }}</span>
                @unless ($app->is_active)
                    <span class="badge badge--bad">Disabled</span>
                @endunless
            </h2>
            <dl class="meta" style="margin-bottom: 16px">
                <dt>Client ID</dt><dd><code>{{ $app->client_id }}</code></dd>
                <dt>Stores seen</dt><dd><a href="{{ route('admin.licenses.index', ['app' => $app->id]) }}">{{ $app->stores_count }}</a></dd>
            </dl>

            <div class="table-wrap">
                <table>
                    <thead><tr><th>Ecwid field</th><th>URL</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($entry['urls'] as [$label, $field, $url, $help])
                        <tr>
                            <td>
                                {{ $label }}
                                <div class="muted" style="font-size: 12px"><code>{{ $field }}</code></div>
                            </td>
                            <td style="white-space: normal">
                                <code class="copy-value">{{ $url }}</code>
                                <div class="muted" style="font-size: 12px">{{ $help }}</div>
                            </td>
                            <td><button type="button" class="btn copy-button" data-copy="{{ $url }}">Copy</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            @if ($entry['legacy'] !== [])
                <details style="margin-top: 12px">
                    <summary class="muted">v1 URLs this app still answers on</summary>
                    <p class="muted" style="margin: 8px 0">These keep working, so stores registered with v1 need no change on day one. Move the Ecwid app to the URLs above when you can.</p>
                    <dl class="meta">
                        @foreach ($entry['legacy'] as [$field, $url])
                            <dt><code>{{ $field }}</code></dt><dd><code>{{ $url }}</code></dd>
                        @endforeach
                    </dl>
                </details>
            @endif
        </div>
    @empty
        <div class="card muted">
            No Ecwid apps registered yet. Add one with
            <code>php artisan ecwid:app &lt;client_id&gt; --gateway=thawani --secret=…</code>,
            or import the v1 apps with <code>php artisan ecwid:import-legacy</code>.
        </div>
    @endforelse

    <div class="card">
        <h2 style="margin-top: 0">Gateway webhooks</h2>
        <p style="margin: 0">Webhook URLs are per store, not per app. Each merchant sees their own URL on the app's settings page in Ecwid, adds it in their Thawani merchant portal, and pastes Thawani's webhook secret back on that page. You can see a store's URL and whether its secret is saved under <a href="{{ route('admin.licenses.index') }}">Licenses</a>.</p>
    </div>

    <div class="card">
        <h2 style="margin-top: 0">Permissions (scopes) the app needs</h2>
        <div class="table-wrap">
            <table>
                <tbody>
                @foreach ($scopes as $scope => $why)
                    <tr><td><code>{{ $scope }}</code></td><td style="white-space: normal">{{ $why }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.querySelectorAll('.copy-button').forEach(function (button) {
            button.addEventListener('click', function () {
                var done = function () {
                    button.textContent = 'Copied';
                    setTimeout(function () { button.textContent = 'Copy'; }, 1500);
                };
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(button.dataset.copy).then(done);
                } else {
                    var input = document.createElement('textarea');
                    input.value = button.dataset.copy;
                    document.body.appendChild(input);
                    input.select();
                    document.execCommand('copy');
                    input.remove();
                    done();
                }
            });
        });
    </script>
@endsection
