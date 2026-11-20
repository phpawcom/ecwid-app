<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>{{ $app->name }}</title>
    <script src="https://d35z3p2poghz10.cloudfront.net/ecwid-sdk/js/1.3.1/ecwid-app.js"></script>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #52606d; margin: 0; padding: 32px; }
    </style>
</head>
<body>
<p id="status">Loading…</p>
<form id="open" method="POST" action="{{ route('ecwid.settings.open', $app) }}">
    <input type="hidden" name="store_id">
    <input type="hidden" name="access_token">
    <input type="hidden" name="lang">
</form>
<script>
(function () {
    'use strict';

    EcwidApp.init({ app_id: @json($app->client_id), autoloadedflag: true, autoheight: true });

    // Ecwid puts the store details after '#' as hex-encoded JSON.
    function payload() {
        try {
            var data = EcwidApp.getPayload();
            if (data && data.store_id) return data;
        } catch (e) {}
        try {
            var hex = window.location.hash.replace(/^#/, '');
            if (!/^([0-9a-f]{2})+$/i.test(hex)) return null;
            return JSON.parse(decodeURIComponent(hex.replace(/(..)/g, '%$1')));
        } catch (e) {
            return null;
        }
    }

    var data = payload();
    if (!data || !data.store_id || !data.access_token) {
        document.getElementById('status').textContent = 'Open this page from your Ecwid admin (My Apps).';
        return;
    }

    var form = document.getElementById('open');
    form.elements.store_id.value = data.store_id;
    form.elements.access_token.value = data.access_token;
    form.elements.lang.value = data.lang || 'en';
    form.submit();
})();
</script>
</body>
</html>
