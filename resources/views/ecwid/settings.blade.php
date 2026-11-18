<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $app->name }}</title>
    <script src="https://d35z3p2poghz10.cloudfront.net/ecwid-sdk/js/1.3.1/ecwid-app.js"></script>
    <link rel="stylesheet" href="https://d35z3p2poghz10.cloudfront.net/ecwid-sdk/css/1.3.8/ecwid-app-ui.css">
    <style>
        .a-card { border: none; border-radius: 0; box-shadow: none; }
        .s4d-status { margin-top: 6px; font-size: 13px; min-height: 18px; }
        .s4d-status--ok { color: #2a8a3e; }
        .s4d-status--error { color: #d0352b; white-space: pre-line; }
        .s4d-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-top: 16px; }
        .s4d-logo img { max-height: 48px; }
        .s4d-copy { display: flex; gap: 8px; align-items: center; }
        .s4d-copy .field { flex: 1; }
    </style>
</head>
<body>
<div class="named-area">
    <div class="named-area__body">
        <div class="a-card a-card--normal">
            <div class="a-card__paddings">
                <div class="payment-method">
                    <div class="payment-method__header">
                        <div class="payment-method__logo s4d-logo">
                            <img src="{{ asset('images/gateways/'.$gateway->key().'-512x512.png') }}" alt="{{ $gateway->name() }}">
                        </div>
                    </div>
                    <div class="payment-method__title">Accept card payments with {{ $gateway->name() }}</div>

                    @if ($loadError)
                        <div class="inline-alert inline-alert--error">{{ $loadError }}</div>
                    @endif

                    <div class="payment-method__content">
                        <div class="form-area">
                            <div class="columned">
                                <div class="columned__item">
                                    <div class="form-area__title">Your {{ $gateway->name() }} account</div>
                                    <div class="form-area__content">
                                        <form id="settings" class="fieldsets-batch" autocomplete="off" onsubmit="return false">
                                            @foreach ($fields as $field)
                                                @if ($field->type === 'checkbox')
                                                    <div class="fieldset">
                                                        <div class="custom-checkbox">
                                                            <label>
                                                                <input type="checkbox" class="custom-checkbox__input" name="{{ $field->key }}" @checked(filter_var($values[$field->key] ?? '', FILTER_VALIDATE_BOOLEAN))>
                                                                <span class="custom-checkbox__label"></span>
                                                                <span class="custom-checkbox__text">{{ $field->label }}</span>
                                                            </label>
                                                            @if ($field->help)
                                                                <div class="custom-checkbox__note muted text-small">{{ $field->help }}</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="fieldset">
                                                        <div class="fieldset__title">{{ $field->label }}</div>
                                                        <div class="field field--medium field--filled">
                                                            <input type="{{ $field->type }}" class="field__input" name="{{ $field->key }}" value="{{ $values[$field->key] ?? '' }}" @required($field->required)>
                                                        </div>
                                                        @if ($field->help)
                                                            <div class="fieldset__note">{{ $field->help }}</div>
                                                        @endif
                                                    </div>
                                                @endif
                                            @endforeach

                                            <div class="fieldset">
                                                <div class="fieldset__title">S4D License</div>
                                                <div class="field field--medium field--filled">
                                                    <input type="text" class="field__input" name="{{ $licenseKey }}" value="{{ $values[$licenseKey] ?? '' }}">
                                                </div>
                                                <div class="s4d-status" id="license-status"></div>
                                            </div>

                                            @if ($webhook)
                                                <div class="fieldset">
                                                    <div class="fieldset__title">Webhook URL</div>
                                                    <div class="s4d-copy">
                                                        <div class="field field--medium field--filled">
                                                            <input type="text" class="field__input" id="webhook-url" value="{{ $webhook['url'] }}" readonly data-skip="1">
                                                        </div>
                                                        <button type="button" class="btn btn-default btn-medium" id="copy-webhook">Copy</button>
                                                    </div>
                                                    <div class="fieldset__note">{{ $webhook['instructions'] }}</div>
                                                    @if ($webhook['lastReceivedAt'])
                                                        <div class="fieldset__note">Last webhook received {{ $webhook['lastReceivedAt']->diffForHumans() }}.</div>
                                                    @endif
                                                </div>
                                                <div class="fieldset">
                                                    <div class="fieldset__title">Webhook secret</div>
                                                    <div class="field field--medium field--filled">
                                                        <input type="password" class="field__input" id="webhook-secret" autocomplete="new-password" data-skip="1"
                                                               placeholder="{{ $webhook['hasSecret'] ? 'Saved. Leave blank to keep it.' : 'Paste the secret from Thawani' }}">
                                                    </div>
                                                    @if ($webhook['hasSecret'])
                                                        <div class="custom-checkbox" style="margin-top: 6px">
                                                            <label>
                                                                <input type="checkbox" class="custom-checkbox__input" id="clear-webhook-secret" data-skip="1">
                                                                <span class="custom-checkbox__label"></span>
                                                                <span class="custom-checkbox__text">Remove the saved secret</span>
                                                            </label>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif

                                            <div class="s4d-actions">
                                                <button type="button" class="btn btn-primary btn-medium" id="save">Save</button>
                                                <button type="button" class="btn btn-default btn-medium" id="verify">Verify {{ $gateway->name() }} keys</button>
                                            </div>
                                            <div class="s4d-status" id="form-status"></div>
                                        </form>
                                    </div>
                                </div>

                                <div class="columned__item columned__item--shifted">
                                    <div class="form-area__title">S4D License</div>
                                    <div class="form-area__content">
                                        <p>You need a license from <a href="https://www.s4d.om/g/ec" target="_blank" rel="noopener">The Source for Development</a>. <a href="https://www.s4d.om/g/d" target="_blank" rel="noopener">How to get the key</a>.</p>
                                        @includeIf('ecwid.settings.'.$gateway->key())
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
                secret.placeholder = clearSecret && clearSecret.checked ? 'Paste the secret from Thawani' : 'Saved. Leave blank to keep it.';
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

    form.elements[licenseField].addEventListener('change', function () { checkLicense(true); });
    if (form.elements[licenseField].value.trim()) checkLicense(false);
})();
</script>
</body>
</html>
