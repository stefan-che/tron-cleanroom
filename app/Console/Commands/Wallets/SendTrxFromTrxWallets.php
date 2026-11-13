<?php
/**
 * php artisan send-trx-from-trx-wallets:run TEXAMPLEADDRESS0000000000000000027 TEXAMPLEADDRESS0000000000000000028 0.0001
 * php artisan send-trx-from-trx-wallets:run TEXAMPLEADDRESS0000000000000000029 TEXAMPLEADDRESS0000000000000000030 50
 */

namespace App\Console\Commands\Wallets;

use App\Helpers\Development;
use App\Repositories\WalletTrxRepository;
use App\Services\Tron\TronService;
use Illuminate\Console\Command;

class SendTrxFromTrxWallets extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send-trx-from-trx-wallets:run {from} {to} {amount}';

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

        $this->process();

        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time, seconds: ' . Development::getFormatDiffMicroTime($timeStart));
    }

    private function process()
    {
        $from = $this->argument('from');
        $to = $this->argument('to');
        $amount = $this->argument('amount');

        $fromWallet = (new WalletTrxRepository())->getByAddress($from);

        if (!$fromWallet) {
            $this->error('From wallet not found');
            return;
        }

        $result = (new TronService())->sendTrxFromTrxWallet($fromWallet, $to, $amount);

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

}
