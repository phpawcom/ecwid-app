<?php

namespace App\Providers;

use App\Logging\GatewayLogger;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(GatewayLogger::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Behind a proxy/load balancer PHP may see plain http. The URLs we hand
        // out (Thawani return URLs, Ecwid setup URLs) must still be https.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
