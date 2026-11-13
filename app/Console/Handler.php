<?php

namespace App\Console;

use App\Console\Commands\Alerts\AlertCheckTransactionStartWallets;
use App\Console\Commands\Alerts\AlertListenerLagBlocks;
use App\Console\Commands\Alerts\AlertNodeDeSyncBlocks;
use App\Console\Commands\Alerts\AlertNodeStopBlocks;
use App\Console\Commands\Alerts\AlertSslCertificate;
use App\Console\Commands\Alerts\AlertTrxWalletBalanceCheck;
use App\Console\Commands\Alerts\AlertWalletsReport;
use App\Console\Commands\Alerts\Ai\AlertAiBalanceCheck;
use App\Console\Commands\Alerts\Aml\AlertAmlBotBalanceCheck;
use App\Console\Commands\Alerts\Aml\AlertAmlCoinKytBalanceCheck;
use App\Console\Commands\Alerts\Aml\AlertAmlWellWalletBalanceCheck;
use App\Console\Commands\Alerts\Energy\AlertEnergyWalletsExpiredByUser;
use App\Console\Commands\Alerts\Energy\AlertRefeeBalanceCheck;
use App\Console\Commands\Alerts\FreeSpaceAlert;
use App\Console\Commands\Aml\CoinKyt\SyncCoinKytMonitoring;
use App\Console\Commands\DeleteExpireEnergy;
use App\Console\Commands\SetCompletedTransactions;
use App\Console\Commands\Transactions\SyncFinishTransactions;
use App\Console\Commands\Transactions\SyncNewTransactions;
use App\Console\Commands\Transactions\SyncOldTransactions;
use App\Console\Commands\Wallets\RefreshTimeOutWallets;
use App\Console\Commands\Wallets\SetTimeOutWallets;
use App\Console\Commands\Wallets\SyncTrxWallets;
use Illuminate\Console\Scheduling\Schedule;

class Handler
{
    public function __invoke(Schedule $schedule): void
    {
        $schedule->command(SyncNewTransactions::class)
            ->withoutOverlapping(2)
            ->everyThreeMinutes();

        $schedule->command(SyncFinishTransactions::class)
            ->withoutOverlapping(11)
            ->hourly();

        $schedule->command(SyncTrxWallets::class)
            ->withoutOverlapping()
            ->hourly();

        $schedule->command(FreeSpaceAlert::class)
            ->hourly();

        $schedule->command(DeleteExpireEnergy::class)
            ->everyMinute();

        $schedule->command(SetCompletedTransactions::class)
            ->hourly();

        $schedule->command(SyncOldTransactions::class)->dailyAt('08:00');
        $schedule->command(AlertTrxWalletBalanceCheck::class)->hourly();
        $schedule->command(AlertRefeeBalanceCheck::class)->hourly();
        $schedule->command(SetTimeOutWallets::class)->hourly();
        $schedule->command(AlertAmlBotBalanceCheck::class)->hourly();
        $schedule->command(AlertAmlWellWalletBalanceCheck::class)->hourly();
        $schedule->command(AlertAmlCoinKytBalanceCheck::class)->hourly();
        $schedule->command(AlertAiBalanceCheck::class)->hourly();
        $schedule->command(AlertListenerLagBlocks::class)->everyThirtyMinutes();
        $schedule->command(AlertNodeDeSyncBlocks::class)->everyTenMinutes();
        $schedule->command(AlertNodeStopBlocks::class)->everyThreeMinutes();
        $schedule->command(AlertCheckTransactionStartWallets::class)->everyFiveMinutes();
        $schedule->command(AlertSslCertificate::class)->dailyAt('10:00');

        $schedule->command(AlertWalletsReport::class)
            ->withoutOverlapping()
            ->dailyAt('08:00')
            ->timezone(config('app.display_timezone', config('app.timezone')));

        $schedule->command(RefreshTimeOutWallets::class)
            ->withoutOverlapping()
            ->dailyAt('06:00')
            ->timezone(config('app.display_timezone', config('app.timezone')));

        $schedule->command(AlertEnergyWalletsExpiredByUser::class)
            ->weeklyOn(5, '14:00') // Friday
            ->timezone(config('app.display_timezone', config('app.timezone')));

        $schedule->command(AlertEnergyWalletsExpiredByUser::class)
            ->weeklyOn(3, '14:00') // Wednesday
            ->timezone(config('app.display_timezone', config('app.timezone')));
    }
}
