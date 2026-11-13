<?php
/**
 * php artisan set-completed-transactions:run
 */

namespace App\Console\Commands;

use App\Enums\WalletStatuses;
use App\Helpers\Development;
use App\Models\Setting;
use App\Models\Wallet\Wallet;
use App\Repositories\WalletRepository;
use App\Services\Wallet\WalletService;
use Illuminate\Console\Command;

class SetCompletedTransactions extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'set-completed-transactions:run';

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

        $this->syncBalance();

        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time (s): ' . Development::getFormatDiffMicroTime($timeStart));
    }

    private function syncBalance(): void
    {
        $wallets = $this->repository->getSyncByCompletedTransactions();

        if (count($wallets) === 0) {
            $this->warn('No wallets to sync balances for');
            return;
        }

        $this->info('Addresses: ' . count($wallets));

        /**
         * @var Wallet $wallet
         */

        $minTrAmount = Setting::getMinTransactionAmount();

        foreach ($wallets as $wallet) {

            $balance = $this->service->getBalance($wallet);
            $this->info(sprintf('Wallet %s balance: %s', $wallet->address, $balance));

            if ($balance < $minTrAmount) {
                $wallet->status = WalletStatuses::COMPLETED->value;
            }

            $wallet->amount = $balance;
            $wallet->save();
        }
    }

}
