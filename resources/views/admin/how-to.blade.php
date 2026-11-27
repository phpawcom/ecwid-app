@extends('admin.layout')
@section('title', 'How-to')
@section('content')
    @php($gatewayKeys = $gateways->keys()->implode('|'))
    <h1>How-to</h1>
    <p class="muted" style="margin-top: -8px; max-width: 760px">
        Commands and steps for adding an Ecwid app (a store or a public listing) for a gateway we already support, and for adding a new payment gateway to the code.
        Run the commands on the server, in <code>{{ $basePath }}</code>.
    </p>

    <div class="card">
        <h2 style="margin-top: 0">Gateways installed</h2>
        <dl class="meta">
            @foreach ($gateways as $key => $name)
                <dt>{{ $name }}</dt><dd><code>{{ $key }}</code></dd>
            @endforeach
        </dl>
    </div>

    <div class="card">
        <h2 style="margin-top: 0">1. Add an Ecwid app for an existing gateway</h2>
        <ol style="padding-left: 20px; margin: 0">
            <li>
                <strong>Create the app on Ecwid.</strong> In the Ecwid developer account (or through Ecwid support for a store's custom app), create the app and note its
                <code>client_id</code> (app namespace, e.g. <code>custom-app-123-1</code>) and <code>client_secret</code>.
                Ask for the scopes listed on <a href="{{ route('admin.ecwid-setup') }}">Ecwid setup</a>.
            </li>
            <li>
                <strong>Register it here</strong> with the gateway it should use:
                <pre style="margin: 8px 0">cd {{ $basePath }}
php artisan ecwid:app &lt;client_id&gt; --gateway={{ $gatewayKeys }} --secret=&lt;client_secret&gt; --name="OmPay (shop.com)"</pre>
                The command prints the three URLs. Running it again for the same <code>client_id</code> updates the app; leave out <code>--secret</code> to keep the saved one.
            </li>
            <li>
                <strong>Give Ecwid the URLs.</strong> Copy <code>paymentUrl</code>, <code>iframeUrl</code> and <code>customJsUrl</code> from <a href="{{ route('admin.ecwid-setup') }}">Ecwid setup</a> into the app's details on Ecwid.
            </li>
            <li>
                <strong>Check the license product.</strong> The gateway must be mapped to its WHMCS product or addon in <code>config/licensing.php</code>.
                If that product has its own "MD5 Hash Verification" secret, it must be in <code>.env</code> (for example <code>LICENSE_SECRET_OMPAY</code>), then run
                <code>php artisan optimize:clear</code>.
            </li>
            <li>
                <strong>Merchant setup.</strong> The merchant installs the app, opens its settings page in Ecwid, enters their gateway keys and S4D license, and adds the webhook URL shown there in their gateway portal.
                The store then appears under <a href="{{ route('admin.licenses.index') }}">Licenses</a>.
            </li>
        </ol>

        <h3>Other app commands</h3>
        <div class="table-wrap">
            <table>
                <tbody>
                <tr><td><code>php artisan ecwid:app &lt;client_id&gt; --disable</code></td><td style="white-space: normal">Stop accepting requests for the app (checkout and settings page stop working).</td></tr>
                <tr><td><code>php artisan ecwid:app &lt;client_id&gt; --enable</code></td><td style="white-space: normal">Accept requests again.</td></tr>
                <tr><td><code>php artisan ecwid:app &lt;client_id&gt; --secret=&lt;new_secret&gt;</code></td><td style="white-space: normal">Save a new client secret after Ecwid rotates it.</td></tr>
                <tr><td><code>php artisan ecwid:app &lt;client_id&gt; --name="New name"</code></td><td style="white-space: normal">Rename the app in this admin.</td></tr>
                <tr><td><code>php artisan ecwid:import-legacy</code></td><td style="white-space: normal">Register the v1 Thawani app from <code>legacy/config.php</code> (answers on the v1 URLs).</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top: 0">2. Add a new payment gateway (code)</h2>
        <p class="muted" style="margin-top: 0">Use OmPay (<code>app/Payments/Gateways/OmPay</code>) as the example to copy. Replace <code>acme</code> / <code>Acme</code> below with the new gateway's key and name.</p>
        <ol style="padding-left: 20px; margin: 0">
            <li>
                <strong>Gateway class.</strong> Create <code>app/Payments/Gateways/Acme/AcmeGateway.php</code> implementing
                <code>App\Payments\Contracts\PaymentGateway</code>:
                <code>key()</code>, <code>name()</code>, <code>settingsFields()</code>, <code>createCheckout()</code>, <code>fetchStatus()</code>, <code>verifyCredentials()</code>.
                If the gateway sends webhooks, also implement <code>HandlesWebhooks</code>: <code>webhookInstructions()</code>, <code>verifyWebhook()</code>, <code>webhookReference()</code>.
                Send API calls through <code>GatewayLogger</code> so they show under <a href="{{ route('admin.logs.index') }}">Logs</a>.
                <pre style="margin: 8px 0">php artisan make:class Payments/Gateways/Acme/AcmeGateway --no-interaction</pre>
            </li>
            <li>
                <strong>Register it</strong> in <code>config/payments.php</code>:
                <pre style="margin: 8px 0">'acme' => \App\Payments\Gateways\Acme\AcmeGateway::class,</pre>
            </li>
            <li>
                <strong>License mapping</strong> in <code>config/licensing.php</code> under <code>gateways</code>. Add <code>secret</code> only if it is sold as its own WHMCS product with its own "MD5 Hash Verification" value:
                <pre style="margin: 8px 0">'acme' => [
    'addons' => ['Acme'],
    'products' => ['Acme'],                   // matched against the WHMCS product name
    'secret' => env('LICENSE_SECRET_ACME'),   // optional
],</pre>
                Add <code>LICENSE_SECRET_ACME=</code> to <code>.env.example</code>, and the real value to <code>.env</code> on each server.
            </li>
            <li>
                <strong>Views and logos</strong> (all optional, picked up by the gateway key):
                <ul style="padding-left: 18px">
                    <li><code>resources/views/ecwid/settings/acme.blade.php</code>: setup steps shown on the merchant's settings page.</li>
                    <li><code>resources/views/ecwid/storefront/acme.blade.php</code>: checkout logos script (falls back to <code>storefront/default</code>).</li>
                    <li><code>public/images/gateways/acme-512x512.png</code> (settings header) and <code>acme-54x34.png</code> (checkout logo).</li>
                </ul>
            </li>
            <li>
                <strong>Tests and style.</strong> Add <code>tests/Feature/AcmeGatewayTest.php</code> (fake the gateway's endpoints with <code>Http::fake</code>), then:
                <pre style="margin: 8px 0">php artisan test --compact
vendor/bin/pint --dirty --format agent</pre>
            </li>
            <li>
                <strong>Deploy.</strong> Commit and push to <code>{{ $branch }}</code>, then on the server:
                <pre style="margin: 8px 0">cd {{ $basePath }}
git pull --ff-only origin {{ $branch }}
php artisan migrate --force   # only if the change adds migrations
php artisan optimize:clear</pre>
                Then register an Ecwid app for it (section 1).
            </li>
        </ol>
    </div>

    <div class="card">
        <h2 style="margin-top: 0">Other commands</h2>
        <div class="table-wrap">
            <table>
                <tbody>
                <tr><td><code>php artisan admin:user &lt;email&gt;</code></td><td style="white-space: normal">Create an admin login, or reset its password.</td></tr>
                <tr><td><code>php artisan payments:reconcile</code></td><td style="white-space: normal">Ask the gateways about pending payments and settle them in Ecwid. Runs every 10 minutes from the scheduler.</td></tr>
                <tr><td><code>php artisan model:prune --model="App\Models\GatewayLog"</code></td><td style="white-space: normal">Delete logs older than the retention set in <a href="{{ route('admin.settings.edit') }}">Settings</a>. Runs daily.</td></tr>
                <tr><td><code>php artisan schedule:list</code></td><td style="white-space: normal">Show the scheduled tasks. The server's cron must run <code>php artisan schedule:run</code> every minute.</td></tr>
                <tr><td><code>php artisan optimize:clear</code></td><td style="white-space: normal">Clear config, route and view caches after changing <code>.env</code> or pulling code.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection
