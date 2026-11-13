<?php

namespace App\Jobs\Wallet\Trx;

use App\Models\Wallet\Wallet;
use App\Models\Wallet\WalletLog;
use App\Repositories\WalletRepository;
use App\Services\Tron\TronService;
use App\Services\Wallet\WalletLoggerService;
use App\Services\Wallet\WalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class SendTrx implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue;

    /**
     * @param int $id
     * @return void
     */
    public function __construct(readonly int $id)
    {
    }

    /**
     * Execute the job.
     * @return void
     * @throws \Exception
     */
    public function handle()
    {
        $walletRepository = new WalletRepository();

        /**
         * @var Wallet $wallet
         */
        $wallet = $walletRepository->getById($this->id);

        if (!$wallet || $wallet->isTestWallet() || !$wallet->address_to || !$wallet->amount_trx) {
            return;
        }

        //Same destination address rule as for USDT
        if (!app(WalletService::class)->isAllowedStoredDestination($wallet)) {
            (new WalletLoggerService)->log(WalletLog::SEND_TRX_FAILED, $wallet, [
                'message' => 'Destination address is not allowed for the wallet colour',
                'address_to' => $wallet->address_to,
            ]);
            return;
        }

        $amount = $wallet->amount_trx - 1.4;// Reserve for network fees

        if ($amount <= 0) {
            (new WalletLoggerService)->log(WalletLog::SEND_TRX_FAILED, $wallet, [
                'message' => 'Not enough amount with commission to send TRX SendToTrxWallet',
                'amount' => $amount,
            ]);
            return;
        }

        $result = (new TronService)->sendTrx(
            $wallet->getDecPrivateKey(),
            $wallet->address,
            $wallet->address_to,
            $amount
        );

        if ($result['success']) {
            $walletRepository->update($wallet, [
                'amount_trx' => 0,
            ]);
        }

        (new WalletLoggerService)->log($result['success'] ? WalletLog::SEND_TRX_SUCCESS :
            WalletLog::SEND_TRX_FAILED, $wallet, $result);
    }
}
