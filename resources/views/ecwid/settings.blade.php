<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $app->name }}</title>
    <script src="https://d35z3p2poghz10.cloudfront.net/ecwid-sdk/js/1.3.1/ecwid-app.js"></script>
    <style>
        :root {
            --text: #1d2533; --muted: #68738a; --border: #d7dce4; --line: #eceff3;
            --bg: #ffffff; --soft: #f6f8fa; --accent: #0a74e6; --accent-dark: #075fc0;
            --ok: #1f8a43; --ok-soft: #e8f6ed; --error: #c9352b; --error-soft: #fdecea; --warn-soft: #fff7e6; --warn-line: #f2c46d;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 14px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }

        .s4d { max-width: 1040px; padding: 28px 32px 32px; }
        .s4d-header { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; }
        .s4d-header img { width: 52px; height: 52px; border-radius: 12px; border: 1px solid var(--line); }
        .s4d-header h1 { margin: 0; font-size: 20px; font-weight: 600; }
        .s4d-header p { margin: 2px 0 0; color: var(--muted); }
        .s4d-mode { margin-left: auto; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 999px; background: var(--soft); color: var(--muted); white-space: nowrap; }
        .s4d-mode--test { background: var(--warn-soft); color: #94600a; }
        .s4d-mode--live { background: var(--ok-soft); color: var(--ok); }

        .s4d-layout { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 28px; align-items: start; }
        @media (max-width: 820px) { .s4d-layout { grid-template-columns: 1fr; } .s4d { padding: 20px 16px; } }

        .s4d-section { border: 1px solid var(--line); border-radius: 10px; padding: 20px 22px; margin-bottom: 16px; }
        .s4d-section h2 { margin: 0 0 4px; font-size: 15px; font-weight: 600; }
        .s4d-section > p { margin: 0 0 16px; color: var(--muted); }

        .s4d-field + .s4d-field { margin-top: 16px; }
        .s4d-field label { display: block; font-weight: 600; margin-bottom: 6px; }
        .s4d-field .req { color: var(--error); font-weight: 400; }
        .s4d-note { margin-top: 6px; font-size: 13px; color: var(--muted); }
        .s4d-input { position: relative; display: flex; gap: 8px; }
        .s4d-input input[type=text], .s4d-input input[type=password], .s4d-input input[type=number] {
            flex: 1; min-width: 0; height: 40px; padding: 0 12px; font: inherit; color: var(--text);
            border: 1px solid var(--border); border-radius: 8px; background: #fff; transition: border-color .15s, box-shadow .15s;
        }
        .s4d-input input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(10, 116, 230, .15); }
        .s4d-input input[readonly] { background: var(--soft); color: var(--muted); }
        .s4d-input input.has-toggle { padding-right: 64px; }
        .s4d-reveal { position: absolute; right: 6px; top: 6px; height: 28px; padding: 0 10px; border: 0; border-radius: 6px; background: var(--soft); color: var(--muted); font: inherit; font-size: 12px; cursor: pointer; }
        .s4d-reveal:hover { color: var(--text); }

        .s4d-field label.s4d-switch { display: flex; gap: 12px; align-items: flex-start; cursor: pointer; font-weight: 400; margin: 0; }
        .s4d-switch input { position: absolute; opacity: 0; pointer-events: none; }
        .s4d-switch .track { flex: none; width: 38px; height: 22px; margin-top: 1px; border-radius: 999px; background: #c5ccd6; position: relative; transition: background .15s; }
        .s4d-switch .track::after { content: ""; position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; border-radius: 50%; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.2); transition: transform .15s; }
        .s4d-switch input:checked + .track { background: var(--accent); }
        .s4d-switch input:checked + .track::after { transform: translateX(16px); }
        .s4d-switch input:focus-visible + .track { box-shadow: 0 0 0 3px rgba(10, 116, 230, .25); }
        .s4d-switch strong { display: block; font-weight: 600; }
        .s4d-switch span.s4d-note { display: block; margin-top: 2px; }

        .btn { height: 40px; padding: 0 18px; border-radius: 8px; font: inherit; font-weight: 600; cursor: pointer; border: 1px solid var(--border); background: #fff; color: var(--text); white-space: nowrap; }
        .btn:hover { background: var(--soft); }
        .btn-primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent-dark); }
        .btn:disabled { opacity: .6; cursor: progress; }

        .s4d-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; padding-top: 4px; }
        .s4d-status { font-size: 13px; }
        .s4d-status:empty { display: none; }
        .s4d-status--ok { color: var(--ok); }
        .s4d-status--error { color: var(--error); white-space: pre-line; }
        .s4d-field .s4d-status { margin-top: 6px; }

        .s4d-segment { display: inline-flex; padding: 3px; border-radius: 9px; background: var(--soft); border: 1px solid var(--line); }
        .s4d-field .s4d-segment label { margin: 0; font-weight: 600; cursor: pointer; }
        .s4d-segment input { position: absolute; opacity: 0; pointer-events: none; }
        .s4d-segment span { display: block; padding: 7px 18px; border-radius: 7px; color: var(--muted); transition: background .15s, color .15s; }
        .s4d-segment input:checked + span { background: #fff; color: var(--text); box-shadow: 0 1px 2px rgba(0,0,0,.12); }
        .s4d-segment input:focus-visible + span { box-shadow: 0 0 0 3px rgba(10, 116, 230, .25); }

        .s4d-keyset { margin-top: 18px; padding: 16px 18px; border: 1px solid var(--line); border-radius: 10px; transition: border-color .15s, opacity .15s; }
        .s4d-keyset__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .s4d-keyset h3 { margin: 0; font-size: 14px; font-weight: 600; }
        .s4d-keyset__tag { display: none; font-size: 12px; font-weight: 600; padding: 2px 10px; border-radius: 999px; background: var(--accent); color: #fff; }
        .s4d-keyset.is-active { border-color: var(--accent); box-shadow: 0 0 0 1px var(--accent); }
        .s4d-keyset.is-active .s4d-keyset__tag { display: inline-block; }
        .s4d-keyset.is-idle { opacity: .7; }
        .s4d-keyset + .s4d-field { margin-top: 18px; }

        .s4d-alert { padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; background: var(--error-soft); color: var(--error); }

        .s4d-aside { position: sticky; top: 16px; }
        .s4d-aside .s4d-section { background: var(--soft); border-color: var(--soft); }
        .s4d-aside h2 { font-size: 14px; }
        .s4d-aside p { margin: 8px 0 0; color: #3e4859; font-size: 13px; }
        .s4d-steps { margin: 10px 0 0; padding-left: 18px; color: #3e4859; font-size: 13px; }
        .s4d-steps li + li { margin-top: 6px; }
    </style>
</head>
<body>
@php($modeField = collect($fields)->first(fn ($f) => $f->type === 'radio' && array_key_exists('true', $f->options)))
<div class="s4d">
    <header class="s4d-header">
        @if (file_exists(public_path('images/gateways/'.$gateway->key().'-512x512.png')))
            <img src="{{ asset('images/gateways/'.$gateway->key().'-512x512.png') }}" alt="">
        @endif
        <div>
            <h1>{{ $gateway->name() }} payments</h1>
            <p>Accept Visa and MasterCard in your Ecwid store through {{ $gateway->name() }}.</p>
        </div>
        @if ($modeField)
            <span class="s4d-mode" id="mode-badge"></span>
        @endif
    </header>

    @if ($loadError)
        <div class="s4d-alert">{{ $loadError }}</div>
    @endif

    <div class="s4d-layout">
        <form id="settings" autocomplete="off" onsubmit="return false">
            <section class="s4d-section">
                <h2>{{ $gateway->name() }} account</h2>
                <p>Save both key pairs, then pick the mode the store uses.</p>

                @foreach (collect($fields)->groupBy(fn ($f) => $f->group ?? '#'.$f->key) as $group => $groupFields)
                    @if (str_starts_with($group, '#'))
                        @include('ecwid.partials.field', ['field' => $groupFields->first()])
                    @else
                        <div class="s4d-keyset" data-active-when="{{ $groupFields->first()->activeWhen }}">
                            <div class="s4d-keyset__head">
                                <h3>{{ $group }}</h3>
                                <span class="s4d-keyset__tag">In use</span>
                            </div>
                            @foreach ($groupFields as $field)
                                @include('ecwid.partials.field', ['field' => $field])
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </section>

            @if ($webhook)
                <section class="s4d-section">
                    <h2>Webhook</h2>
                    <p>{{ $webhook['instructions'] }}</p>

                    <div class="s4d-field">
                        <label for="webhook-url">Webhook URL</label>
                        <div class="s4d-input">
                            <input type="text" id="webhook-url" value="{{ $webhook['url'] }}" readonly data-skip="1">
                            <button type="button" class="btn" id="copy-webhook">Copy</button>
                        </div>
                        @if ($webhook['lastReceivedAt'])
                            <div class="s4d-note">Last webhook received {{ $webhook['lastReceivedAt']->diffForHumans() }}.</div>
                        @endif
                    </div>
                    <div class="s4d-field">
                        <label for="webhook-secret">Webhook secret</label>
                        <div class="s4d-input">
                            <input type="password" id="webhook-secret" class="has-toggle" autocomplete="new-password" spellcheck="false" data-skip="1"
                                   placeholder="{{ $webhook['hasSecret'] ? 'Saved. Leave blank to keep it.' : 'Paste the secret from '.$gateway->name() }}">
                            <button type="button" class="s4d-reveal" data-reveal="webhook-secret">Show</button>
                        </div>
                    </div>
                    @if ($webhook['hasSecret'])
                        <div class="s4d-field">
                            <label class="s4d-switch">
                                <input type="checkbox" id="clear-webhook-secret" data-skip="1">
                                <span class="track"></span>
                                <span><strong>Remove the saved secret</strong></span>
                            </label>
                        </div>
                    @endif
                </section>
            @endif

            <section class="s4d-section">
                <h2>S4D license</h2>
                <p>Your license key from <a href="https://www.s4d.om/g/ec" target="_blank" rel="noopener">The Source for Development</a>.</p>
                <div class="s4d-field">
                    <label for="store-domain">Store domain</label>
                    <div class="s4d-input">
                        <input type="text" id="store-domain" value="{{ $storeDomain ?? 'Not known yet' }}" readonly data-skip="1">
                    </div>
                    <div class="s4d-note">The license is checked for this domain, taken from your Ecwid store URL.</div>
                </div>
                <div class="s4d-field">
                    <label for="f-license">License key</label>
                    <div class="s4d-input">
                        <input type="text" id="f-license" name="{{ $licenseKey }}" value="{{ $values[$licenseKey] ?? '' }}" spellcheck="false" placeholder="ECWID-…">
                    </div>
                    <div class="s4d-status" id="license-status"></div>
                </div>
            </section>

            <div class="s4d-actions">
                <button type="button" class="btn btn-primary" id="save">Save settings</button>
                <button type="button" class="btn" id="verify">Test {{ $gateway->name() }} keys</button>
                <div class="s4d-status" id="form-status"></div>
            </div>
        </form>

        <aside class="s4d-aside">
            <section class="s4d-section">
                <h2>Getting started</h2>
                <ol class="s4d-steps">
                    <li>Get an S4D license. <a href="https://www.s4d.om/knowledgebase/2/Downloads-and-Licenses.html" target="_blank" rel="noopener">How to get the key</a></li>
                    @includeIf('ecwid.settings.'.$gateway->key())
                    <li>Save, then use <b>Test {{ $gateway->name() }} keys</b> to check them.</li>
                </ol>
            </section>
        </aside>
    </div>
</div>

<script>
(function () {
    'use strict';

    EcwidApp.init({ app_id: @json($app->client_id), autoloadedflag: true, autoheight: true });

    var context = @json($contextToken);
    var urls = {
        save: @json(route('ecwid.settings.save', $app)),
        license: @json(route('ecwid.settings.license', $app)),
        verify: @json(route('ecwid.settings.verify', $app))
    };
    var licenseField = @json($licenseKey);
    var form = document.getElementById('settings');

    function settings() {
        var data = {};
        Array.prototype.forEach.call(form.elements, function (el) {
            if (!el.name || el.dataset.skip) return;
            if (el.type === 'radio' && !el.checked) return;
            data[el.name] = el.type === 'checkbox' ? el.checked : el.value;
        });
        return data;
    }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Settings-Context': context },
            body: JSON.stringify(body)
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (json) {
                if (!res.ok && !json.message) json.message = 'The app server returned an error (HTTP ' + res.status + ').';
                if (!res.ok) json.ok = false;
                return json;
            });
        }).catch(function () {
            return { ok: false, message: 'Could not reach the app server.' };
        });
    }

    function status(id, result) {
        var el = document.getElementById(id);
        el.textContent = result.message || '';
        el.className = 's4d-status ' + (result.ok ? 's4d-status--ok' : 's4d-status--error');
    }

    function busy(button, on) {
        button.disabled = on;
        button.classList.toggle('btn-loading', on);
    }

    document.getElementById('save').addEventListener('click', function () {
        var button = this;
        busy(button, true);
        var body = { settings: settings() };
        var secret = document.getElementById('webhook-secret');
        var clearSecret = document.getElementById('clear-webhook-secret');
        if (secret) body.webhook_secret = secret.value;
        if (clearSecret) body.clear_webhook_secret = clearSecret.checked;
        post(urls.save, body).then(function (result) {
            status('form-status', result);
            busy(button, false);
            if (result.ok && secret && (secret.value || (clearSecret && clearSecret.checked))) {
                secret.value = '';
                secret.placeholder = clearSecret && clearSecret.checked ? @json('Paste the secret from '.$gateway->name()) : 'Saved. Leave blank to keep it.';
                if (clearSecret) clearSecret.checked = false;
            }
        });
    });

    document.getElementById('verify').addEventListener('click', function () {
        var button = this;
        busy(button, true);
        post(urls.verify, { settings: settings() }).then(function (result) {
            status('form-status', result);
            busy(button, false);
        });
    });

    function checkLicense(refresh) {
        var value = form.elements[licenseField].value.trim();
        if (!value) {
            status('license-status', { ok: false, message: '' });
            return;
        }
        status('license-status', { ok: true, message: 'Checking license…' });
        post(urls.license, { license: value, refresh: refresh === true }).then(function (result) {
            status('license-status', result);
        });
    }

    var copyWebhook = document.getElementById('copy-webhook');
    if (copyWebhook) {
        copyWebhook.addEventListener('click', function () {
            var input = document.getElementById('webhook-url');
            input.select();
            var done = function () { copyWebhook.textContent = 'Copied'; setTimeout(function () { copyWebhook.textContent = 'Copy'; }, 1500); };
            if (navigator.clipboard) {
                navigator.clipboard.writeText(input.value).then(done, function () { document.execCommand('copy'); done(); });
            } else {
                document.execCommand('copy');
                done();
            }
        });
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-reveal]'), function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.dataset.reveal);
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Hide' : 'Show';
        });
    });

    var badge = document.getElementById('mode-badge');
    var modeField = @json($modeField?->key);
    if (modeField) {
        var keysets = document.querySelectorAll('.s4d-keyset[data-active-when]');
        var showMode = function () {
            var mode = form.elements[modeField].value;
            if (badge) {
                badge.textContent = mode === 'true' ? 'Test mode' : 'Live';
                badge.className = 's4d-mode ' + (mode === 'true' ? 's4d-mode--test' : 's4d-mode--live');
            }
            Array.prototype.forEach.call(keysets, function (set) {
                var active = set.dataset.activeWhen === mode;
                set.classList.toggle('is-active', active);
                set.classList.toggle('is-idle', !active);
            });
        };
        Array.prototype.forEach.call(form.querySelectorAll('input[name="' + modeField + '"]'), function (radio) {
            radio.addEventListener('change', showMode);
        });
        showMode();
    }

    form.elements[licenseField].addEventListener('change', function () { checkLicense(true); });
    if (form.elements[licenseField].value.trim()) checkLicense(false);
})();
</script>
</body>
</html>
