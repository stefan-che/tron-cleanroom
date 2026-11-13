<?php
/**
 * php artisan send-trx-to-trx-wallets:run
 * php artisan send-trx-to-trx-wallets:run --address=TEXAMPLEADDRESS0000000000000000023
 */

namespace App\Console\Commands\Wallets;

use App\Helpers\Development;
use App\Jobs\Wallet\Trx\SendToTrxWallet;
use App\Models\Wallet\Wallet;
use App\Models\WalletTrx;
use App\Repositories\WalletRepository;
use Illuminate\Console\Command;

class SendTrxToTrxWallets extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send-trx-to-trx-wallets:run {--address= : Wallet address to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send unused TRX from completed wallets to the TRX wallet';

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
        /**
         * @var Wallet $wallet
         * @var WalletTrx $walletTrx
         */
        $walletTrx = WalletTrx::oldest()->first(); // TODO: add a wallet parameter

        $wallets = $this->getWallets();

        $this->info('Wallets: ' . count($wallets));

        $this->output->progressStart(count($wallets));

        foreach ($wallets as $wallet) {

            $this->info(sprintf('Wallet: %s %s', $wallet->id, $wallet->address));

            SendToTrxWallet::dispatchSync($wallet->id, $walletTrx->id);
            sleep(2);

            $this->output->progressAdvance();

        }

        $this->output->progressFinish();
    }

    private function getWallets(): \Illuminate\Database\Eloquent\Collection
    {
        $repository = new WalletRepository();

        if ($address = $this->option('address')) {
            $query = $repository->query()
                ->where('address', $address);
            return $query->latest()->get();
        }

        $query = $repository->query()
            ->completed()
            ->where('amount_trx', '>=', 0.2)
            ->updatedOldDays(3);

        return $query->latest()->get();
    }

}
