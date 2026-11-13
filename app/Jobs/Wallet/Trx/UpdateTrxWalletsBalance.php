<?php

namespace App\Jobs\Wallet\Trx;

use App\Models\WalletTrx;
use App\Repositories\WalletTrxRepository;
use App\Services\Tron\TronService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Updates TRX wallet balances
 */
class UpdateTrxWalletsBalance implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue;

    /**
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $repository = new WalletTrxRepository();
        $wallets = $repository->query()->get();

        if (count($wallets) === 0) {
            return ;
        }

        $service = new TronService;
        /**
         * @var WalletTrx $wallet
         */
        foreach ($wallets as $wallet) {
            $amountTrx = $service->getBalanceTrx($wallet->address);

            if ($amountTrx <= 0.1) {
                continue;
            }

            $repository->update($wallet, [
                'amount' => $amountTrx,
            ]);

            sleep(8);
        }
    }
}
