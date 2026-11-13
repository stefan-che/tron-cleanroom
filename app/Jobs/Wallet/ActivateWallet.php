<?php

namespace App\Jobs\Wallet;

use App\Helpers\ArrayHelper;
use App\Helpers\TelegramHelper;
use App\Models\Setting;
use App\Models\Wallet\Wallet;
use App\Models\Wallet\WalletLog;
use App\Models\WalletTrx;
use App\Repositories\WalletRepository;
use App\Repositories\WalletTrxRepository;
use App\Services\Tron\TronService;
use App\Services\Wallet\WalletLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class ActivateWallet implements ShouldQueue, ShouldBeUnique
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
    public function handle()
    {
        /**
         * @var Wallet $toWallet
         */
        $toWallet = (new WalletRepository)->getById($this->id);

        if (!$toWallet || $toWallet->isTestWallet()) {
            return;
        }

        $amount = Setting::getCountTrxForActivateWallet();

        if ($toWallet->amount_trx >= $amount) {
            return;
        }

        /**
         * @var WalletTrx $fromW
         */
        $fromW = (new WalletTrxRepository)->getRandomWallet();

        if (!$fromW) {
            (new WalletLoggerService)->log(WalletLog::ACTIVATE_FAILED, $toWallet);

            $message = [
                '❌ Failed to activate the wallet: TRX wallets lack the required amount (' . $amount . ' TRX).' => "\n\n",
                'Payment' => TelegramHelper::copyTelegramStyle($toWallet->external_id_format) . "\n",
                'Wallet' => TelegramHelper::copyTelegramStyle($toWallet->address) . "\n",
                'Link' => $toWallet->link_view_admin . "\n",
            ];
            $this->sendMessage($message);
            return;
        }

        $result = (new TronService)->sendTrx(
            $fromW->private_key,
            $fromW->address,
            $toWallet->address,
            $amount
        );

        (new WalletLoggerService)->log($result['success'] ? WalletLog::ACTIVATE_SUCCESS :
            WalletLog::ACTIVATE_FAILED, $toWallet, $result);

        if ($result['success']) {
            $toWallet->amount_trx = $amount;
            $toWallet->save();
        } else {
            $message = [
                '❌ Failed to activate the wallet.' => "\n\n",
                'id' => TelegramHelper::copyTelegramStyle($toWallet->id) . "\n",
                'Payment' => TelegramHelper::copyTelegramStyle($toWallet->external_id_format) . "\n",
                'Wallet' => TelegramHelper::copyTelegramStyle($toWallet->address) . "\n",
                'Error' => 'TRX wallets probably have no balance; see the logs for the exact reason' . "\n",
                'Link' => $toWallet->link_view_admin . "\n",
            ];

            $this->sendMessage($message);
        }
    }

    private function sendMessage(array $message): void
    {
        TelegramHelper::sendMessage(ArrayHelper::implodeWithKeys($message, ' '));
    }

    public function uniqueId(): string
    {
        return 'activate_wallet_' . $this->id;
    }
}
