<?php

namespace App\Services\Wallet;

use App\Enums\WalletOperationsTypes;
use App\Enums\WalletStatuses;
use App\Helpers\Development;
use App\Jobs\Aml\DispatchTransactionChecks;
use App\Jobs\Wallet\SendUsdt;
use App\Models\Aml\AmlAddress;
use App\Models\Recipient\Wallet as RecipientWallet;
use App\Models\Setting;
use App\Models\Wallet\Transaction;
use App\Models\Wallet\Wallet;
use App\Models\Wallet\WalletLog;
use \App\Repositories\Recipient\RecipientWalletRepository;
use App\Repositories\WalletRepository;
use App\Services\Aml\AmlChecksManager;
use App\Services\Aml\AmlServiceDispatcher;
use App\Services\Tron\TronService;
use App\Services\Tron\TronscanService;
use Illuminate\Support\Facades\Log;

class WalletService
{

    protected $walletRepository;

    public function __construct()
    {
        $this->walletRepository = new WalletRepository;
    }


    public function generateWallet(string  $blockchain,
                                   string  $externalId,
                                   string  $recipientId,
                                   bool    $isTest,
                                   int     $operationType = WalletOperationsTypes::BUY->value,
                                   ?string $addressTo = null
    ): ?Wallet
    {
        $bcTypes = RecipientWallet::blockChainTypesNames();
        $trc20 = $bcTypes[RecipientWallet::BLOCKCHAIN_TYPE_TRC_20];

        if ($blockchain === $trc20) {
            return (new TronService())->generateWallet($externalId, $recipientId, $isTest, $operationType, $addressTo);
        }

        $btc = $bcTypes[RecipientWallet::BLOCKCHAIN_TYPE_BTC];
        $eth = $bcTypes[RecipientWallet::BLOCKCHAIN_TYPE_ETH];

        if ($blockchain === $btc) {
        }
        if ($blockchain === $eth) {
        }
        return null;

    }

    public function getBalance(Wallet $wallet): float
    {
        if ($wallet->isTrc20()) {
            return (new TronService())->getBalanceUsdt($wallet->address);
        }

        return 0.0;
    }

    public function getTransactions(Wallet $wallet): array
    {
        if ($wallet->isTrc20()) {
            return (new TronService())->getTransactions($wallet->address);
        }

        return [];
    }

    public function getTransactionsByTronscan(Wallet $wallet, int $limit = 20): array
    {
        if ($wallet->isTrc20()) {
            return (new TronscanService())->getTransactions($wallet, $limit);
        }

        return [];
    }

    public function setStatusNew(Wallet $wallet): bool
    {
        return $this->walletRepository->update($wallet, [
            'status' => WalletStatuses::NEW->value,
            'has_error' => false,
        ]);
    }

    public function setStatusCompleted(Wallet $wallet): bool
    {
        return $this->walletRepository->update($wallet, [
            'status' => WalletStatuses::COMPLETED->value,
            'has_error' => false,
        ]);
    }

    public function setWhiteWallet(Wallet $wallet): bool
    {
        if ($wallet->address_to) {
            return false;
        }

        $recipientWallet = (new RecipientWalletRepository)->getRecipientWalletByType($wallet->recipient_id, RecipientWallet::TYPE_WHITE);

        if (!$recipientWallet) {
            return false;
        }

        //Fix the risk tier together with the address - SendUsdt validates the destination by it
        return $this->walletRepository->update($wallet, [
            'address_to' => $recipientWallet->address,
            'type' => RecipientWallet::TYPE_WHITE,
        ]);
    }

    public function setGrayWallet(Wallet $wallet): bool
    {
        if ($wallet->address_to) {
            return false;
        }

        $recipientWallet = (new RecipientWalletRepository)->getRecipientWalletByType($wallet->recipient_id, RecipientWallet::TYPE_GRAY);

        if (!$recipientWallet) {
            return false;
        }

        //Fix the risk tier together with the address - SendUsdt validates the destination by it
        return $this->walletRepository->update($wallet, [
            'address_to' => $recipientWallet->address,
            'type' => RecipientWallet::TYPE_GRAY,
        ]);
    }

    public function setYellowWallet(Wallet $wallet): bool
    {
        if ($wallet->address_to) {
            return false;
        }

        $recipientWallet = (new RecipientWalletRepository)->getRecipientWalletByType($wallet->recipient_id, RecipientWallet::TYPE_YELLOW);

        if (!$recipientWallet) {
            return false;
        }

        //Fix the risk tier together with the address - SendUsdt validates the destination by it
        return $this->walletRepository->update($wallet, [
            'address_to' => $recipientWallet->address,
            'type' => RecipientWallet::TYPE_YELLOW,
        ]);
    }

    public function setStatusTransferMoneyProcessFinish(Wallet $wallet): bool
    {
        return $this->walletRepository->update($wallet, [
            'status' => WalletStatuses::TRANSFER_MONEY_PROCESS_FINISH->value,
            'has_error' => false,
        ]);
    }

    public function setStatusCheckTransactionFinish(Wallet $wallet): bool
    {
        return $this->walletRepository->update($wallet, [
            'status' => WalletStatuses::CHECK_TRANSACTION_FINISH->value,
            'has_error' => false,
        ]);
    }

    public function reCalcUsdtBalance(Wallet $wallet): bool
    {
        $amount = (new TronService())->getBalanceUsdt($wallet->address);

        return $wallet->update([
            'amount' => $amount,
        ]);
    }

    /**
     * Sends funds from the wallet.
     *
     * @param Wallet $wallet
     * @param int $type - send type (white / grey / black)
     * @param string|null $toAddress - destination address
     * @param float|null $amount - partial send amount, null - send the whole balance
     * @return bool
     */
    public function send(Wallet $wallet, int $type, ?string $toAddress = '', ?float $amount = null): bool
    {
        if (
            $wallet->isSendCompleted() ||
            !$wallet->amount ||
            $wallet->isTransferMoneyProcessStart()) {
            return false;
        }

        //Never send more than the balance; the remainder goes in full
        if ($amount !== null) {
            $amount = min($amount, (float)$wallet->amount);

            if ($amount <= 0) {
                return false;
            }
        }

        $recipientWallet = null;

        if ($toAddress) {
            $recipientWallet = (new RecipientWalletRepository)->getByAddress($toAddress);
        } elseif (!$wallet->address_to) {
            $recipientWallet = (new RecipientWalletRepository)->getRecipientWalletByType($wallet->recipient_id, $type);
            if (!$recipientWallet) {
                return false;
            }
            $toAddress = $recipientWallet->address;
        }

        $addressTo = $wallet->address_to ?: $toAddress;

        //For the black tier the destination may differ from the stored one - use the given address
        if (
            $wallet->isOperationBuy() &&
            $type === RecipientWallet::TYPE_BLACK &&
            $toAddress && $toAddress !== $wallet->address_to
        ) {
            (new WalletLoggerService)->log(WalletLog::SET_WALLET_TO, $wallet, [
                'old_address' => $wallet->address_to,
                'new_address' => $toAddress,
                'source' => 'send',
            ]);

            $addressTo = $toAddress;
        }

        //Incoming (buy): white/grey/yellow - only a wallet of the same recipient and the same tier
        if (
            ($toAddress && !$this->isAllowedDestination($wallet, $type, $toAddress)) ||
            !$this->isAllowedDestination($wallet, $type, $addressTo)
        ) {
            Log::channel('tron')->warning('WalletService::send - destination address not allowed for risk tier', [
                'wallet' => $wallet->id,
                'type' => $type,
                'to_address' => $toAddress,
                'address_to' => $addressTo,
            ]);

            return false;
        }

        $this->walletRepository->update($wallet, [
            'status' => WalletStatuses::TRANSFER_MONEY_PROCESS_START->value,
            'address_to' => $addressTo,
            'type' => $type,
            'recipient_wallet_id' => $wallet->recipient_wallet_id ?: $recipientWallet?->id,
        ]);

        if ($wallet->isTrc20()) {
            SendUsdt::dispatch($wallet->id, false, $amount);
            return true;
        }

        return false;
    }

    /**
     * Whether wallet funds may be sent to this address for the selected risk tier.
     *
     * - payout (sell): the address is set by the client, no restriction;
     * - incoming (buy), black: refund to sender - any address, but not a recipient wallet of another tier;
     * - incoming (buy), white/grey/yellow: only a wallet of the same recipient (recipient_id) and the same tier;
     * - incoming (buy) without a tier (address chosen before the tier was fixed): only a wallet of the same recipient.
     *
     * @param Wallet $wallet
     * @param int|null $type - risk tier (RecipientWallet::TYPE_*)
     * @param string|null $address - destination address
     * @return bool
     */
    public function isAllowedDestination(Wallet $wallet, ?int $type, ?string $address): bool
    {
        $address = trim((string)$address);

        if (!$address) {
            return false;
        }

        if (!$wallet->isOperationBuy()) {
            return true;
        }

        $recipientWallet = (new RecipientWalletRepository)->getByAddress($address);

        if ($type === RecipientWallet::TYPE_BLACK) {
            return !$recipientWallet || (int)$recipientWallet->type === RecipientWallet::TYPE_BLACK;
        }

        if (!$recipientWallet || (int)$recipientWallet->recipient_id !== (int)$wallet->recipient_id) {
            return false;
        }

        return $type === null || (int)$recipientWallet->type === $type;
    }

    /**
     * Validates the stored destination address (address_to) against the stored risk tier (type).
     * Last check before the actual send: SendUsdt / SendTrx.
     *
     * @param Wallet $wallet
     * @return bool
     */
    public function isAllowedStoredDestination(Wallet $wallet): bool
    {
        $type = $wallet->type !== null ? (int)$wallet->type : null;

        return $this->isAllowedDestination($wallet, $type, $wallet->address_to);
    }

    /**
     * Changes the destination address. Payout (sell) operations only.
     * Every change is written to the wallet logs (old / new).
     *
     * @param Wallet $wallet
     * @param string $addressTo - new destination address
     * @return bool
     */
    public function changeAddressToForSell(Wallet $wallet, string $addressTo): bool
    {
        $addressTo = trim($addressTo);

        if (!$wallet->isOperationSell() || !$addressTo) {
            return false;
        }

        //Funds are already being sent; too late to change the address
        if ($wallet->isTransferMoneyProcessStart()) {
            return false;
        }

        $oldAddress = $wallet->address_to;

        if ($oldAddress === $addressTo) {
            return true;
        }

        if (!$this->walletRepository->updateAddressTo($wallet, $addressTo)) {
            return false;
        }

        (new WalletLoggerService)->log(WalletLog::SET_WALLET_TO, $wallet, [
            'old_address' => $oldAddress,
            'new_address' => $addressTo,
            'source' => 'api',
        ]);

        return true;
    }

    /**
     * Whether funds of a payout (sell) operation can be sent automatically.
     * The destination address is set on wallet creation; no AML check for such operations.
     *
     * @param Wallet $wallet
     * @return bool
     */
    public function isReadyForAutoSendSell(Wallet $wallet): bool
    {
        if (!$wallet->isOperationSell() || $wallet->isTestWallet()) {
            return false;
        }

        if (!$wallet->address_to || !$wallet->amount) {
            return false;
        }

        if ($wallet->send_tx_hash || $wallet->isTransferMoneyProcessStart()) {
            return false;
        }

        return $wallet->isTrc20();
    }

    /**
     * Automatic send of funds for payout (sell) operations.
     * Sends to the destination address set on wallet creation.
     *
     * @param Wallet $wallet
     * @return bool
     */
    public function autoSendForSell(Wallet $wallet): bool
    {
        if (!Setting::isAutoSendSellEnabled()) {
            return false;
        }

        if (!$this->isReadyForAutoSendSell($wallet)) {
            return false;
        }

        return $this->send($wallet, RecipientWallet::TYPE_BLACK, $wallet->address_to);
    }

    /**
     * @param Transaction $transaction
     * @param int $delayMinutes - delay in minutes
     * @return void
     */
    public function runAmlTransaction(Transaction $transaction, int $delayMinutes = 0): void
    {
        //Reset the wallet's aml_risk_score to start
        (new WalletRepository())->update($transaction->wallet, [
            'aml_risk_score' => null,
            'aml_file_report_link' => null,
        ]);

        if ($delayMinutes) {
            DispatchTransactionChecks::dispatch($transaction->id)
                ->delay(now()->addMinutes(Development::isLocal() ? 0 : $delayMinutes));
        } else {
            $dispatcher = app(AmlServiceDispatcher::class);
            $dispatcher->dispatchTransactionChecks($transaction);
        }
    }

    /**
     * @param Transaction $transaction
     * @return void
     */
    public function runRefreshStatusAmlTransaction(Transaction $transaction): void
    {
        $dispatcher = app(AmlServiceDispatcher::class);
        $dispatcher->updateAmlStatusTransaction($transaction);
    }

    /**
     * Force-completes the transaction AML check.
     * Used when one provider hangs but at least one has already returned a result.
     *
     * @param Transaction $transaction
     * @return bool - true if finalized
     */
    public function forceCompleteAmlTransaction(Transaction $transaction): bool
    {
        return app(AmlChecksManager::class)->forceCompleteTransaction($transaction);
    }

    /**
     * Force-completes the address AML check.
     * Used when one provider hangs but at least one has already returned a result.
     *
     * @param AmlAddress $amlAddress
     * @return bool - true if finalized
     */
    public function forceCompleteAmlAddress(AmlAddress $amlAddress): bool
    {
        return app(AmlChecksManager::class)->forceCompleteAddress($amlAddress);
    }
}
