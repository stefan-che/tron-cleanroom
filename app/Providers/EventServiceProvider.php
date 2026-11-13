<?php

namespace App\Providers;

use App\Events\Energy\RecipientWalletAddressChanged;
use App\Listeners\Energy\SyncEnergyWalletOnAddressChange;
use App\Listeners\Ledger\SyncLedgerWalletOnAddressChange;
use App\Listeners\User\SendLoginNotification;
use App\Listeners\User\LogAuthenticationAttempt;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        RecipientWalletAddressChanged::class => [
            SyncEnergyWalletOnAddressChange::class,
            SyncLedgerWalletOnAddressChange::class,
        ],

        Login::class => [
            LogAuthenticationAttempt::class . '@handleLogin',
            SendLoginNotification::class,
        ],

        Failed::class => [
            LogAuthenticationAttempt::class . '@handleFailed',
        ],

    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
