<?php
/**
 * php artisan sync-new:transactions:run
 */

namespace App\Console\Commands\Transactions;

use App\Helpers\Development;
use App\Models\Wallet\Wallet;
use App\Repositories\WalletRepository;
use App\Services\Wallet\WalletService;
use Illuminate\Console\Command;

class SyncNewTransactions extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-new:transactions:run';

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
        /**
         * todo: refactor these 2 commands
         */
        $wallets = $this->repository->getSyncTransactions();

        if (count($wallets) === 0) {
            $this->warn('No new wallets to check');
            return;
        }

        $this->info('Addresses: ' . count($wallets));

        /**
         * @var Wallet $wallet
         */

        $this->output->progressStart(count($wallets));

        foreach ($wallets as $wallet) {

            if ($wallet->isTestWallet()) {
                $this->info(sprintf('Wallet %s is a test wallet, skipping sync', $wallet->address));
                continue;
            }

            sleep(3);

            try {
                $transactions = $this->service->getTransactionsByTronscan($wallet, 10);
            } catch (\Exception $exception) {
                $transactions = $this->service->getTransactions($wallet);
            }

            if (!$transactions) {
                $this->info(sprintf('No transactions for wallet %s', $wallet->address));
                continue;
            }

            $this->repository->syncTransactions($wallet, $transactions);
            $this->info(sprintf('%s transactions for wallet %s', count($transactions), $wallet->address));

            $this->output->progressAdvance();

        }

        $this->output->progressFinish();

    }

}
