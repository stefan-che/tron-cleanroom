<?php
/**
 * php artisan sync-old-transactions:run
 */

namespace App\Console\Commands\Transactions;

use App\Helpers\Development;
use App\Models\Wallet\Wallet;
use App\Repositories\WalletRepository;
use App\Services\Wallet\WalletService;
use Illuminate\Console\Command;

class SyncOldTransactions extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-old-transactions:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';


    private WalletRepository $repository;
    private WalletService $service;

    public function __construct(
        WalletRepository $repository,
        WalletService    $service,
    )
    {
        parent::__construct();
        $this->repository = $repository;
        $this->service = $service;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeStart = microtime(true);

        $this->sync();

        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time, seconds: ' . Development::getFormatDiffMicroTime($timeStart));
    }

    private function sync(): void
    {
        $wallets = $this->repository->getSyncOldTransactions();

        if (count($wallets) === 0) {
            $this->warn('No wallets to check');
            return;
        }

        $this->info('Addresses: ' . count($wallets));

        /**
         * @var Wallet $wallet
         */

        $this->output->progressStart(count($wallets));

        foreach ($wallets as $wallet) {

            if ($wallet->isTestWallet()) {
                continue;
            }

            sleep(3);

            $transactions = $this->service->getTransactions($wallet);

            if (!$transactions) {
                continue;
            }

            $this->repository->syncTransactions($wallet, $transactions);

            $this->output->progressAdvance();
        }

        $this->output->progressFinish();
    }

}
