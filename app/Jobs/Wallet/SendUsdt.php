<?php

namespace App\Jobs\Wallet;

use App\Enums\WalletStatuses;
use App\Helpers\ArrayHelper;
use App\Helpers\TelegramHelper;
use App\Jobs\Wallet\Trx\AddTrxWallet;
use App\Models\Wallet\Wallet;
use App\Models\Wallet\WalletLog;
use App\Repositories\WalletRepository;
use App\Services\Tron\TronService;
use App\Services\Wallet\WalletLoggerService;
use App\Services\Wallet\WalletService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendUsdt implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue;

    /**
     * @param int $id
     * @param bool $energyRetry Retry with doubled energy (for exchange wallets)
     * @param float|null $amount Partial send amount; null sends the whole wallet balance
     * @return void
     */
    public function __construct(
        readonly int    $id,
        readonly bool   $energyRetry = false,
        readonly ?float $amount = null,
    )
    {
    }

    /**
     * Execute the job.
     * @return void
     * @throws \Exception
     */
    public function handle()
    {
        $walletRepository = new WalletRepository;

        /**
         * @var Wallet $wallet
         */
        $wallet = $walletRepository->getById($this->id);

        if (!$wallet || $wallet->isTestWallet() || !$wallet->address_to || !$wallet->amount) {
            return;
        }

        //Deposit (buy): a free address only for black, otherwise a wallet of the same recipient and color
        if (!app(WalletService::class)->isAllowedStoredDestination($wallet)) {
            Log::channel('tron')->error('SendUsdt: destination address not allowed for color ' . $wallet->address, [
                'wallet' => $wallet->id,
                'type' => $wallet->type,
                'address_to' => $wallet->address_to,
            ]);

            (new WalletLoggerService)->log(WalletLog::SEND_USDT_FAILED, $wallet, [
                'message' => 'Destination address is not allowed for the wallet colour',
                'address_to' => $wallet->address_to,
            ]);

            $walletRepository->update($wallet, ['has_error' => true]);

            return;
        }

        AddEnergy::dispatchSync($wallet->id, '', $this->energyRetry); //dispatchSync

        $wallet->refresh();

        //Re-check after refresh: address_to may have changed while energy was being rented
        if (!app(WalletService::class)->isAllowedStoredDestination($wallet)) {
            Log::channel('tron')->error('SendUsdt: destination address not allowed for color ' . $wallet->address, [
                'wallet' => $wallet->id,
                'type' => $wallet->type,
                'address_to' => $wallet->address_to,
            ]);

            (new WalletLoggerService)->log(WalletLog::SEND_USDT_FAILED, $wallet, [
                'message' => 'Destination address is not allowed for the wallet colour',
                'address_to' => $wallet->address_to,
            ]);

            $walletRepository->update($wallet, ['has_error' => true]);

            return;
        }

        if (!$wallet->amount_energy) {
            $this->release(1 * 60);
            return;
        }

        //Compute the amount after refresh - the balance may have changed while energy was being rented
        $amount = $this->getSendAmount($wallet);

        if (!$amount) {
            return;
        }

        sleep(6);

        $service = new TronService();

        $result = $service->sendUsdtFromWallet($wallet, $amount);
        $isOk = $result['result'] ?? false;

        if (!empty($result['code']) && $result['code'] === TronService::STATUS_NAME_BANDWITH_ERROR) {

            Log::channel('tron')->error('SendUsdt BANDWITH_ERROR ' . $wallet->address, [
                'address' => $wallet->address,
                'result' => $result,
            ]);

            AddTrxWallet::dispatchSync($wallet->id, 1.2); //dispatch

            $this->release(15);
            return;
        }

        $outOfEnergy = false;

        if (!$isOk) {
            sleep(7);
            $status = $service->statusTransaction($result['txid'] ?? '');
            $isOk = $status === TronService::STATUS_NAME_SUCCESS;
            $outOfEnergy = $status === TronService::STATUS_NAME_OUT_OF_ENERGY;
        }

        // The transaction was broadcast but ran out of energy (exchange wallet).
        // Retry once with doubled energy. If that fails too, add no more.
        if (!$isOk && $outOfEnergy && !$this->energyRetry) {
            Log::channel('tron')->warning('SendUsdt OUT_OF_ENERGY, retrying with doubled energy ' . $wallet->address, [
                'wallet' => $wallet->id,
                'address' => $wallet->address,
                'result' => $result,
            ]);

            (new WalletLoggerService)->log(WalletLog::SEND_USDT_FAILED, $wallet, $result);

            self::dispatch($wallet->id, true, $this->amount)->delay(now()->addSeconds(15));

            return;
        }

        //Wallet balance left after sending. Above 0 means the send was partial
        $rest = round((float)$wallet->amount - $amount, TronService::USDT_DECIMALS);
        $isPartial = $rest > 0;

        (new WalletLoggerService)->log($isOk ? WalletLog::SEND_USDT_SUCCESS :
            WalletLog::SEND_USDT_FAILED, $wallet, $result + [
                'amount' => $amount,
                'is_partial' => $isPartial,
            ]);

        if ($isOk) {
            $walletRepository->update($wallet, [
                'last_transfer_amount' => $amount,
                'has_error' => false,
                'amount_energy' => 0,
                'amount' => $isPartial ? $rest : 0,
                'send_tx_hash' => $result['txid'] ?? '',
                //On a partial send, return the wallet to the ready state - the remainder can be sent again
                'status' => $isPartial
                    ? WalletStatuses::CHECK_TRANSACTION_FINISH->value
                    : WalletStatuses::TRANSFER_MONEY_PROCESS_FINISH->value,
            ]);

            //Close transactions only once the wallet is fully emptied
            if (!$isPartial) {
                $wallet->transactions()
                    ->whereNull('send_tx_hash')
                    ->update([
                        'send_at' => Carbon::now(),
                        'send_tx_hash' => $result['txid'] ?? '',
                    ]);
            }

            return;
        }

        Log::channel('tron')->error('❌ USDT send error ' . $wallet->address, [
            'wallet' => $wallet->id,
            'address' => $wallet->address,
            'result' => $result,
        ]);

        $walletRepository->update($wallet, [
            'has_error' => true,
        ]);

        $message = [
            '❌ USDT send error' => "\n\n",
            'id' => TelegramHelper::copyTelegramStyle($wallet->id) . "\n",
            'Payment' => TelegramHelper::copyTelegramStyle($wallet->external_id_format) . "\n",
            'Wallet' => TelegramHelper::copyTelegramStyle($wallet->address) . "\n",
            'Link' => $wallet->link_view_admin . "\n",
        ];

        TelegramHelper::sendMessage(ArrayHelper::implodeWithKeys($message, ' '));
    }

    /**
     * Send amount: the given partial amount (capped at the balance) or the whole wallet balance.
     *
     * @param Wallet $wallet
     * @return float
     */
    private function getSendAmount(Wallet $wallet): float
    {
        $amount = $this->amount !== null
            ? min($this->amount, (float)$wallet->amount)
            : (float)$wallet->amount;

        if ($amount <= 0) {
            return 0;
        }

        //Round to USDT precision. floor is wrong here: 0.000249 * 1_000_000 in double gives 248.99...,
        //the amount loses a unit and a full send is wrongly treated as partial
        return round($amount, TronService::USDT_DECIMALS);
    }
}
