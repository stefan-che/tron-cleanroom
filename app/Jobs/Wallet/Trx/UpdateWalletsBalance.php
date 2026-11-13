<?php

namespace App\Jobs\Wallet\Trx;

use App\Models\Wallet\Wallet;
use App\Repositories\WalletRepository;
use App\Services\Tron\TronService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Updates TRX balances of operation wallets
 */
class UpdateWalletsBalance implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue;

    /**
     * @return void
     */
    public function __construct(readonly ?int $walletId = null)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $repository = new WalletRepository();
        $query = $repository->query();

        if ($this->walletId) {
            $query->where('id', $this->walletId);
        }

        $wallets = $query->get();

        if (count($wallets) === 0) {
            return;
        }

        $service = new TronService;
        /**
         * @var Wallet $wallet
         */
        foreach ($wallets as $wallet) {
            $amountTrx = $service->getBalanceTrx($wallet->address);

            if ($amountTrx <= 0.1) {
                continue;
            }

            $repository->update($wallet, [
                'amount_trx' => $amountTrx,
            ]);

            if (!$this->walletId) {
                sleep(3);
            }
        }
    }
}
