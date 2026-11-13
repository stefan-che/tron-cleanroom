<?php
/**
 * php artisan sync-trx-balance-wallets:run
 */

namespace App\Console\Commands\Wallets;

use App\Helpers\Development;
use App\Jobs\Wallet\Trx\UpdateWalletsBalance;
use Illuminate\Console\Command;

class SyncTrxBalanceWallets extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-trx-balance-wallets:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeStart = microtime(true);

        UpdateWalletsBalance::dispatchSync();

        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time, seconds: ' . Development::getFormatDiffMicroTime($timeStart));
    }

}
