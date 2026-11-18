@if ($log->success)
    <span class="badge badge--ok">{{ $log->response_status ?? 'OK' }}</span>
@else
    <span class="badge badge--bad">{{ $log->response_status ?? 'Failed' }}</span>
@endif
