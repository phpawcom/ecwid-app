@extends('admin.layout')
@section('title', 'Sign in')
@section('content')
    <div class="login card">
        <h1>Sign in</h1>
        <form method="post" action="{{ route('admin.login.attempt') }}" class="stack">
            @csrf
            <div>
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>
            <div>
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
            </div>
            <label class="muted" style="font-weight: normal"><input type="checkbox" name="remember" value="1" style="width: auto"> Keep me signed in</label>
            <button class="btn btn--primary" type="submit">Sign in</button>
        </form>
    </div>
@endsection
