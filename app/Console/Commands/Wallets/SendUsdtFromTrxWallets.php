<?php
/**
 * php artisan send-usdt-from-trx-wallets:run TEXAMPLEADDRESS0000000000000000024 TEXAMPLEADDRESS0000000000000000003 30
 * php artisan send-usdt-from-trx-wallets:run TEXAMPLEADDRESS0000000000000000025 TEXAMPLEADDRESS0000000000000000026 1000
 */

namespace App\Console\Commands\Wallets;

use App\Helpers\Development;
use App\Repositories\WalletTrxRepository;
use App\Services\Tron\TronService;
use Illuminate\Console\Command;

class SendUsdtFromTrxWallets extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send-usdt-from-trx-wallets:run {from} {to} {amount}';

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

        $result = (new TronService())->sendUsdtFromTrxWallet($fromWallet, $to, $amount);

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

}
