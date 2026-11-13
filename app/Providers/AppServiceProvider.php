<?php

namespace App\Providers;

use App\Services\Tron\TronNodeDispatcher;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Telegram\Bot\Laravel\Facades\Telegram;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One list of nodes and their connections per request/command
        $this->app->singleton(TronNodeDispatcher::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Log Viewer access gate: authenticated users only
        Gate::define('viewLogViewer', function ($user = null) {
            return $user !== null;
        });
    }
}
