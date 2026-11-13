<?php
/**
 * Sends a Telegram reminder if there are enabled auto-top-up wallets
 * php artisan alert:energy-wallets-expired
 */

namespace App\Console\Commands\Alerts\Energy;

use App\Helpers\TelegramHelper;
use App\Models\Energy\EnergyWallet;
use App\Reports\ExpiredEnergyWalletsReport;
use Illuminate\Console\Command;

/**
 * @deprecated
 */
class AlertEnergyWalletsExpired extends Command
{
    protected $signature = 'alert:energy-wallets-expired';
    protected $description = 'Reminder about enabled auto-top-up wallets that have expired';

    public function handle(): void
    {
        $expired = EnergyWallet::query()
            ->where('auto_replenishment_enabled', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->with('recipient')
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired enabled wallets.');
            return;
        }

        $message = app(ExpiredEnergyWalletsReport::class)->buildAlertMessage($expired);

        TelegramHelper::sendMessage($message);

        $this->warn("Reminder sent: {$expired->count()} expired wallets.");
    }
}
