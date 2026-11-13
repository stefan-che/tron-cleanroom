<?php

namespace App\Jobs\Wallet\Trx;

use App\Models\Wallet\Wallet;
use App\Models\Wallet\WalletLog;
use App\Models\WalletTrx;
use App\Repositories\WalletRepository;
use App\Repositories\WalletTrxRepository;
use App\Services\Tron\TronService;
use App\Services\Wallet\WalletLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class SendToTrxWallet implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue;

    /**
     * @param int $from
     * @param int $to
     * @return void
     */
    public function __construct(readonly int $from, readonly int $to)
    {
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle()
    {
        $walletRepository = new WalletRepository();
        $trxWalletRepository = new WalletTrxRepository();

        /**
         * @var Wallet $toWallet
         * @var WalletTrx $toWallet
         */
        $fromWallet = $walletRepository->getById($this->from);
        $toWallet = $trxWalletRepository->getById($this->to);

        if (!$fromWallet || !$toWallet) {
            return;
        }

        UpdateWalletsBalance::dispatchSync($fromWallet->id);
        $fromWallet->refresh();

        $amount = $fromWallet->amount_trx - 1.1;// Reserve for network fees

        if ($amount <= 0) {
            (new WalletLoggerService)->log(WalletLog::SEND_TRX_FAILED, $fromWallet, [
                'message' => 'Not enough amount with commission to send TRX SendToTrxWallet',
                'amount' => $amount,
            ]);
            return;
        }

        $result = (new TronService)->sendTrx(
            $fromWallet->getDecPrivateKey(),
            $fromWallet->address,
            $toWallet->address,
            $amount
        );

        if ($result['success']) {
            $walletRepository->update($fromWallet, [
                'amount_trx' => 0,
            ]);
        }

        (new WalletLoggerService)->log($result['success'] ? WalletLog::SEND_TRX_SUCCESS :
            WalletLog::SEND_TRX_FAILED, $fromWallet, $result);
    }
}
