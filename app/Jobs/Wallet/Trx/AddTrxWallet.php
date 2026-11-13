<?php

namespace App\Jobs\Wallet\Trx;

use App\Helpers\TelegramHelper;
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

class AddTrxWallet implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue;

    /**
     * @param int $id
     * @param float $amount
     * @return void
     */
    public function __construct(readonly int $id, readonly float $amount)
    {
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle()
    {
        $walletRepository = new WalletRepository;

        /**
         * @var Wallet $toWallet
         */
        $toWallet = $walletRepository->getById($this->id);

        if (!$toWallet || $toWallet->isTestWallet()) {
            return;
        }

        /**
         * @var WalletTrx $fromW
         */
        $fromW = (new WalletTrxRepository)->getRandomWallet();

        if (!$fromW) {
            (new WalletLoggerService)->log(WalletLog::ADD_TRX_FAILED, $toWallet);
            TelegramHelper::sendMessage('❌ Failed to send TRX: no TRX wallets available to top up.');
            return;
        }

        $result = (new TronService)->sendTrx(
            $fromW->private_key,
            $fromW->address,
            $toWallet->address,
            $this->amount
        );

        if ($result['success']) {
            $walletRepository->update($toWallet, [
                'amount_trx' => $this->amount,
            ]);
        }

        (new WalletLoggerService)->log($result['success'] ? WalletLog::ADD_TRX_SUCCESS : WalletLog::ADD_TRX_FAILED, $toWallet, $result);
    }
}
