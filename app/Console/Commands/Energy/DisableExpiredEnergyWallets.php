<?php
/**
 * Disables auto-top-up pool wallets whose expires_at has passed.
 * php artisan energy:disable-expired
 */

namespace App\Console\Commands\Energy;

use App\Enums\Energy\EnergyWalletEvent;
use App\Models\Energy\EnergyWallet;
use App\Services\Energy\EnergyServiceDispatcher;
use Illuminate\Console\Command;

class DisableExpiredEnergyWallets extends Command
{
    protected $signature = 'energy:disable-expired';
    protected $description = 'Disable auto-top-up for wallets with an expired expires_at';

    public function __construct(private readonly EnergyServiceDispatcher $dispatcher)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $expired = EnergyWallet::query()
            ->where('auto_replenishment_enabled', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired wallets.');
            return;
        }

        $this->info("Expired wallets found: {$expired->count()}");

        foreach ($expired as $wallet) {
            // Turn the flag off - the observer calls smartEnergy stop
            $wallet->update(['auto_replenishment_enabled' => false]);

            // Also record the reason in the history
            $wallet->addHistory(
                EnergyWalletEvent::EXPIRED,
                'cron',
                "Automatically disabled on expiration: {$wallet->expires_at->format('d.m.Y H:i')}"
            );

            $this->line("  ✓ {$wallet->address} (id={$wallet->id})");
        }

        $this->info('Done.');
    }
}
