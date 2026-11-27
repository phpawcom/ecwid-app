<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Admin') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
</head>
<body>
@auth
    <header class="topbar">
        <div class="topbar__inner">
            <span class="topbar__brand">{{ config('app.name') }}</span>
            <nav>
                <a href="{{ route('admin.logs.index') }}" @class(['active' => request()->routeIs('admin.logs.*', 'admin.transactions.*')])>Logs</a>
                <a href="{{ route('admin.licenses.index') }}" @class(['active' => request()->routeIs('admin.licenses.*')])>Licenses</a>
                <a href="{{ route('admin.ecwid-setup') }}" @class(['active' => request()->routeIs('admin.ecwid-setup')])>Ecwid setup</a>
                <a href="{{ route('admin.how-to') }}" @class(['active' => request()->routeIs('admin.how-to')])>How-to</a>
                <a href="{{ route('admin.settings.edit') }}" @class(['active' => request()->routeIs('admin.settings.*')])>Settings</a>
            </nav>
            <form method="post" action="{{ route('admin.logout') }}">
                @csrf
                <button class="btn btn--link" type="submit">Sign out</button>
            </form>
        </div>
    </header>
@endauth
<main class="main">
    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="errors">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif
    @yield('content')
</main>
</body>
</html>
