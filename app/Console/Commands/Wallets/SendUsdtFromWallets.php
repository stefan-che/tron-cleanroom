<?php
/**
 * php artisan send-usdt-from-wallets:run TEXAMPLEADDRESS0000000000000000031 TEXAMPLEADDRESS0000000000000000032 7342
 * php artisan send-usdt-from-wallets:run TEXAMPLEADDRESS0000000000000000033 TEXAMPLEADDRESS0000000000000000003 2
 * php artisan send-usdt-from-wallets:run TEXAMPLEADDRESS0000000000000000033 TEXAMPLEADDRESS0000000000000000034 2
 */

namespace App\Console\Commands\Wallets;

use App\Helpers\Development;
use App\Jobs\Wallet\ActivateWallet;
use App\Jobs\Wallet\AddEnergy;
use App\Repositories\WalletRepository;
use App\Services\Tron\TronService;
use Illuminate\Console\Command;

class SendUsdtFromWallets extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send-usdt-from-wallets:run {from} {to} {amount}';

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
        $toAddress = $this->argument('to');
        $amount = $this->argument('amount');

        $fromWallet = (new WalletRepository())->getByAddress($from);

        if (!$fromWallet) {
            $this->error('From wallet not found');
            return;
        }

        $this->table(
            ['Id', 'From', 'To', 'Amount Wallet', 'Send Amount'],
            [
                [
                    $fromWallet->id,
                    $fromWallet->address,
                    $toAddress,
                    $fromWallet->amount,
                    $amount,
                ],
            ],
        );

        if (!$this->confirm('  ⚠️  Are you sure? This is a real request!')) {
            $this->line("  ℹ️  Operation cancelled");
            return;
        }

        ActivateWallet::dispatchSync($fromWallet->id);
        sleep(3);

        AddEnergy::dispatchSync($fromWallet->id, $toAddress);

        sleep(5);

        $service = app(TronService::class);

        $result = $service->sendUsdt(
            $fromWallet->getDecPrivateKey(),
            $fromWallet->address,
            $toAddress,
            $amount
        );

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        sleep(5);

        $amount = $service->getBalanceUsdt($fromWallet->address);

        $fromWallet->update([
            'amount' => $amount,
        ]);

        $this->info('Operation completed, balance updated');
    }

}
