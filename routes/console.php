<?php

use App\Models\GatewayLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('payments:reconcile')->everyTenMinutes()->withoutOverlapping();
Schedule::command('model:prune', ['--model' => [GatewayLog::class]])->daily();
