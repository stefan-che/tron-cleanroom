<?php
/**
 * php artisan sync-trx-wallets:run
 */

namespace App\Console\Commands\Wallets;

use App\Helpers\Development;
use App\Jobs\Wallet\Trx\UpdateTrxWalletsBalance;
use Illuminate\Console\Command;

class SyncTrxWallets extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-trx-wallets:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync TRX wallet balances';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeStart = microtime(true);

        UpdateTrxWalletsBalance::dispatchSync();

        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time, seconds: ' . Development::getFormatDiffMicroTime($timeStart));
    }

}
