<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment</title>
    @isset($ecwidAppId)
        {{-- Inside the Ecwid admin: without init() Ecwid hides this page behind its own error. --}}
        <script src="https://d35z3p2poghz10.cloudfront.net/ecwid-sdk/js/1.3.1/ecwid-app.js"></script>
        <script>EcwidApp.init({ app_id: @json($ecwidAppId), autoloadedflag: true, autoheight: true });</script>
    @endisset
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f6f7f9; color: #1f2933; margin: 0; }
        .box { max-width: 480px; margin: 12vh auto; background: #fff; border-radius: 8px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        h1 { font-size: 18px; margin: 0 0 12px; }
        p { margin: 0; line-height: 1.5; color: #52606d; }
    </style>
</head>
<body>
<div class="box">
    <h1>Something went wrong</h1>
    <p>{{ $message }}</p>
</div>
</body>
</html>
