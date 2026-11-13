<?php

/**
 * php artisan refresh-timeout-wallets:run
 */

namespace App\Console\Commands\Wallets;

use App\Enums\WalletStatuses;
use App\Helpers\Development;
use App\Models\Setting;
use App\Models\Wallet\Wallet;
use App\Repositories\WalletRepository;
use App\Services\Wallet\WalletService;
use Illuminate\Console\Command;

class RefreshTimeOutWallets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refresh-timeout-wallets:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily pass over timed-out wallets to check them for funds';

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
        $this->info('Execution time, seconds: ' . Development::getFormatDiffMicroTime($timeStart));
    }

    private function syncBalance(): void
    {
        $wallets = $this->repository
            ->query()
            ->timeOut()
            ->latest()
            ->syncUpdatedDays(14)
            ->get();

        if (count($wallets) === 0) {
            $this->warn('No wallets to sync balances for');
            return;
        }

        $this->info('Addresses: ' . count($wallets));

        /**
         * @var Wallet $wallet
         */

        $minTrAmount = Setting::getAmlSumCheck();

        $this->output->progressStart(count($wallets));

        foreach ($wallets as $wallet) {
            $this->output->progressAdvance();

            sleep(2);

            $balance = 0;
            try {
                $balance = $this->service->getBalance($wallet);
                if ($balance < $minTrAmount) {
                    continue;
                }
            } catch (\Exception $exception) {
                $this->error($exception->getMessage());
            }

            $this->info(sprintf('Wallet %s balance: %s', $wallet->address, $balance));

            $wallet->status = WalletStatuses::NEW->value;
            $wallet->save();
        }

        $this->output->progressFinish();
    }


}
