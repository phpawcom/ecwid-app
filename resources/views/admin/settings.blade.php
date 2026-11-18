@extends('admin.layout')
@section('title', 'Settings')
@section('content')
    <h1>Settings</h1>

    <form method="post" action="{{ route('admin.settings.update') }}" class="card stack" style="max-width: 560px">
        @csrf
        @method('put')
        <h2 style="margin-top: 0">Gateway logs</h2>
        <p class="muted" style="margin: 0">Every request to a payment gateway and every Ecwid order update is logged with its request and response. Gateway requests include the shopper's name, phone and email, so this also sets how long that personal data is kept.</p>
        <div>
            <label for="log_retention">Keep logs for</label>
            <select id="log_retention" name="log_retention">
                @foreach ($options as $option)
                    <option value="{{ $option->value }}" @selected($option === $retention)>{{ $option->label() }}{{ $option === \App\Logging\LogRetention::default() ? ' (default)' : '' }}</option>
                @endforeach
            </select>
        </div>
        <p class="muted" style="margin: 0">
            {{ number_format($logCount) }} log {{ str('entry')->plural($logCount) }} stored{{ $oldestLog ? ', oldest from '.\Illuminate\Support\Carbon::parse($oldestLog)->format('Y-m-d') : '' }}.
            Older entries are deleted once a day.
        </p>
        <div><button class="btn btn--primary" type="submit">Save</button></div>
    </form>

    @if ($retention->days() !== null)
        <form method="post" action="{{ route('admin.settings.prune') }}" class="card" style="max-width: 560px"
              onsubmit="return confirm('Delete all logs older than {{ $retention->label() }} now?')">
            @csrf
            <p style="margin-top: 0">Delete logs older than <strong>{{ $retention->label() }}</strong> now instead of waiting for the daily cleanup.</p>
            <button class="btn" type="submit">Delete expired logs now</button>
        </form>
    @endif
@endsection
