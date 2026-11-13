<?php

namespace App\Jobs\Wallet;

use App\Helpers\ArrayHelper;
use App\Helpers\TelegramHelper;
use App\Models\Setting;
use App\Models\Wallet\Wallet;
use App\Repositories\WalletRepository;
use App\Services\Wallet\WalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Automatic sending of funds for "payout" (sell) operations.
 * Runs only when Setting::AUTO_SEND_SELL_ENABLED is on.
 */
class AutoSendSellWallet implements ShouldQueue, ShouldBeUnique
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
     */
    public function handle(): void
    {
        if (!Setting::isAutoSendSellEnabled()) {
            return;
        }

        /**
         * @var Wallet $wallet
         */
        $wallet = (new WalletRepository)->getById($this->id);

        if (!$wallet) {
            return;
        }

        $service = app(WalletService::class);

        if (!$service->isReadyForAutoSendSell($wallet)) {
            return;
        }

        //Wallet not activated yet, waiting for TRX to pay network fees
        if (!$wallet->amount_trx) {
            $this->release(60);
            return;
        }

        $result = $service->autoSendForSell($wallet);

        Log::channel('tron')->info('AutoSendSellWallet ' . $wallet->address, [
            'wallet' => $wallet->id,
            'address_to' => $wallet->address_to,
            'amount' => $wallet->amount,
            'result' => $result,
        ]);

        if (!$result) {
            return;
        }

        TelegramHelper::sendMessage(ArrayHelper::implodeWithKeys([
            '🤖 Automatic payment send (payout)' => "\n\n",
            'Payment' => TelegramHelper::copyTelegramStyle($wallet->external_id_format) . "\n",
            'Wallet' => TelegramHelper::copyTelegramStyle($wallet->address) . "\n",
            'Recipient' => TelegramHelper::copyTelegramStyle($wallet->address_to) . "\n",
            'Amount' => $wallet->amount . " USDT\n",
            'Link' => $wallet->link_view_admin . "\n",
        ], ' '));
    }

    public function uniqueId(): string
    {
        return 'auto_send_sell_wallet_' . $this->id;
    }
}
