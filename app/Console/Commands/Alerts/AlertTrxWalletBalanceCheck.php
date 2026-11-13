<?php
/**
 * php artisan alert-trx-wallet-balance-check:run
 */

namespace App\Console\Commands\Alerts;

use App\Helpers\Development;
use App\Helpers\TelegramHelper;
use App\Repositories\WalletTrxRepository;
use Illuminate\Console\Command;

class AlertTrxWalletBalanceCheck extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alert-trx-wallet-balance-check:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeStart = microtime(true);

        $this->check();

        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time (s): ' . Development::getFormatDiffMicroTime($timeStart));
    }

    private function check(): void
    {
        if (!Development::isProduction()) {
            return;
        }

        $sum = (new WalletTrxRepository)->getSumAllWallet();

        $this->info('Total of all TRX wallets: '. $sum);

        if ($sum < 60) {
            $txt = sprintf('❌ TRX wallets balance (%s) is running low', $sum);
            $this->alert($txt);
            TelegramHelper::sendMessage($txt);
            return;
        }

        $this->alert('TRX wallets balance is OK');

    }
}
