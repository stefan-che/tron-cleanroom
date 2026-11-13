<?php

namespace App\Repositories;

use App\Enums\WalletOperationsTypes;
use App\Enums\WalletStatuses;
use App\Helpers\Development;
use App\Helpers\StringHelper;
use App\Jobs\Wallet\ActivateWallet;
use App\Jobs\Wallet\AutoSendSellWallet;
use App\Models\Setting;
use App\Models\Wallet\Wallet;
use App\Services\Tron\Tron;
use App\Services\Wallet\WalletService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class WalletRepository extends Repository
{

    /**
     * @return Builder|Wallet
     */
    public function query(): Builder
    {
        return Wallet::query();
    }


    public function getById(int $id): ?Wallet
    {
        return $this->query()
            ->where('id', $id)
            ->first();
    }

    public function getByAddress(string $address): ?Wallet
    {
        return $this->query()
            ->where('address', $address)
            ->with(['transactions.checks.service'])
            ->first();
    }

    public function getByExternalId(string $externalId): ?Wallet
    {
        return $this->query()
            ->where('external_id', $externalId)
            ->first();
    }

    /**
     * Find a wallet by ID, address or external_id
     * Detects the search type from the string format
     *
     * @param string $searchValue Search value (ID, address or external_id)
     * @return array{wallet: Wallet|null, searchType: string} Array with the wallet and the search type
     */
    public function findByAddressOrExternalId(string $searchValue): array
    {
        $searchValue = trim($searchValue);

        if (ctype_digit($searchValue)) {
            $wallet = $this->getById((int)$searchValue);
            $searchType = 'ID';
        } elseif (strlen($searchValue) === 34 && str_starts_with($searchValue, 'T')) {
            $wallet = $this->getByAddress($searchValue);
            $searchType = 'address';
        } else {
            $wallet = $this->getByExternalId(StringHelper::getExternalId($searchValue));
            $searchType = 'number';
        }

        return [
            'wallet' => $wallet,
            'searchType' => $searchType,
        ];
    }

    /**
     * @param array $data
     * @return Wallet|null
     */
    public function store(array $data): ?Wallet
    {
        $data = [
            'status' => $data['status'] ?? WalletStatuses::NEW->value,
            'address' => $data['address'],
            'blockchain_type' => $data['blockchain_type'],
            'address_hex' => $data['address_hex'],
            'external_id' => $data['external_id'],
            'recipient_id' => $data['recipient_id'],
            'operation_type' => $data['operation_type'] ?? WalletOperationsTypes::default()->value,
            'address_to' => $data['address_to'] ?? null,

            'private_key' => $data['private_key'],
            'private_key_hash' => $data['private_key_hash'],
            'private_key_salt' => $data['private_key_salt'],
            'private_key_secret' => $data['private_key_secret'],
            'public_key' => $data['public_key'],
            'is_test' => $data['is_test'] ?? false,
            'connect_type' => $data['connect_type'] ?? Setting::getCryptoConnect(),
            'saved_key' => $data['saved_key'] ?? null,
        ];

        try {
            return $this->query()->create($data);
        } catch (\Exception $exception) {
            Log::error('Failed to save wallet address', $data + [
                    'exception' => $exception->getMessage()
                ]);
        }

        return null;
    }

    public function update(Wallet $wallet, array $data): bool
    {
        $data = [
            'status' => $data['status'] ?? $wallet->status,
            'type' => $data['type'] ?? $wallet->type,
            'address_to' => $data['address_to'] ?? $wallet->address_to,
            'send_tx_hash' => $data['send_tx_hash'] ?? $wallet->send_tx_hash,
            'amount_energy' => $data['amount_energy'] ?? $wallet->amount_energy,
            'amount_trx' => $data['amount_trx'] ?? $wallet->amount_trx,
            'amount' => $data['amount'] ?? $wallet->amount,
            'comment' => $data['comment'] ?? $wallet->comment,
            'tx_id' => $data['tx_id'] ?? $wallet->tx_id,
            'aml_risk_score' => array_key_exists('aml_risk_score', $data) ? $data['aml_risk_score'] : $wallet->aml_risk_score,
            'aml_file_report_link' => $data['aml_file_report_link'] ?? $wallet->aml_file_report_link,
            'has_error' => array_key_exists('has_error', $data) ? $data['has_error'] : $wallet->has_error,
            'is_test' => array_key_exists('is_test', $data) ? $data['is_test'] : $wallet->is_test,
            'expire_energy_at' => $data['expire_energy_at'] ?? $wallet->expire_energy_at,
            'error' => $data['error'] ?? $wallet->error,
            'last_transfer_amount' => $data['last_transfer_amount'] ?? $wallet->last_transfer_amount,
            'energy_balance' => $data['energy_balance'] ?? $wallet->energy_balance,
            'recipient_wallet_id' => array_key_exists('recipient_wallet_id', $data) ? $data['recipient_wallet_id'] : $wallet->recipient_wallet_id,
        ];

        try {
            return $wallet->update($data);
        } catch (\Exception $exception) {
            Log::error('Failed to update wallet', $data + [
                    'exception' => $exception->getMessage(),
                    'wallet' => $wallet->id,
                ]);
        }

        return false;
    }

    /**
     * Change the destination address for funds
     *
     * @param Wallet $wallet
     * @param string $addressTo - new destination address
     * @return bool
     */
    public function updateAddressTo(Wallet $wallet, string $addressTo): bool
    {
        $addressTo = trim($addressTo);

        if (!$addressTo) {
            return false;
        }

        try {
            return $wallet->update([
                'address_to' => $addressTo,
            ]);
        } catch (\Exception $exception) {
            Log::error('Failed to change destination address', [
                'wallet' => $wallet->id,
                'address_to' => $addressTo,
                'exception' => $exception->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Get the list of wallets by status
     * @param array $filtered
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function paginateByFilter(array $filtered = [], int $perPage = 50): LengthAwarePaginator
    {
        $perPage = max(1, min(100, (int)$perPage));

        $query = $this->query()->latest('id');

        if (!empty($filtered['status'])) {
            $status = $filtered['status'];
            $statusInt = null;

            if (is_numeric($status)) {
                $statusInt = (int)$status;
            } else if (is_string($status)) {
                $flip = array_flip(Wallet::getStatusesEn());
                $key = strtolower(trim($status));
                if (array_key_exists($key, $flip)) {
                    $statusInt = (int)$flip[$key];
                }
            }

            if ($statusInt !== null) {
                $query->where('status', $statusInt);
            }
        }

        if (!empty($filtered['recipient_id'])) {
            $query->whereHas('recipient', function ($q) use ($filtered) {
                $q->where('external_id', $filtered['recipient_id']);
            });
        }

        return $query->paginate($perPage);
    }

    public function getSyncTransactions()
    {
        return $this->query()
            ->latest('updated_at')
            ->syncNewTransaction()
            ->syncUpdatedDays()
            ->get();
    }

    public function getSyncOldTransactions()
    {
        return $this->query()
            ->latest('updated_at')
            ->syncOldTransaction()
            ->syncUpdatedDays(31 * 1) // 1 month
            ->get();
    }

    /**
     * Completed
     * @return mixed
     */
    public function getSyncByCompletedTransactions()
    {
        return $this->query()
            ->syncForCompleted()
            ->latest('updated_at')
            ->get();
    }

    /**
     * Transfer of funds finished
     * @return mixed
     */
    public function getSyncByFinishTransactions()
    {
        return $this->query()
            ->syncFinishTransaction()
            ->latest('updated_at')
            ->get();
    }

    public function getAlertReport(array $recipientIds = [])
    {
        return $this->query()
            ->with(['recipient'])
            ->where('amount', '>', 1)
            ->whereIn('status', [
                WalletStatuses::NEW->value,
                WalletStatuses::ADD_TRANSACTION->value,
                WalletStatuses::CHECK_TRANSACTION_START->value,
                WalletStatuses::CHECK_TRANSACTION_FINISH->value,
                WalletStatuses::TRANSFER_MONEY_PROCESS_START->value,
            ])
            ->when(!empty($recipientIds), fn($q) => $q->whereIn('recipient_id', $recipientIds))
            ->notTest()
            ->latest()
            ->get();
    }

    public function syncTransactions(Wallet $wallet, array $transactions): void
    {
        $trRepository = new TransactionRepository();

        $minTrAmount = Setting::getMinTransactionAmount();

        foreach ($transactions as $txData) {

            $contractAddress = $txData['token_info']['address'] ?? Tron::USDT_TRC20_CONTRACT;
            if ($contractAddress !== Tron::USDT_TRC20_CONTRACT) { // Only TRC20 transactions are relevant
                continue;
            }

            if ($txData['to'] !== $wallet->address) { // Only incoming transactions are relevant
                continue;
            }

            $amount = $txData['value'] / 1_000_000;

            if ($amount < $minTrAmount) {
                $wallet->comment = 'Transaction below the minimum allowed amount detected (likely dust)';
                $wallet->save();
                continue;
            }

            $existDb = $trRepository->getByTxId($txData['transaction_id']);

            if ($existDb) {
                continue;
            }

            $wallet->status = WalletStatuses::ADD_TRANSACTION->value;
            $wallet->amount += $amount;
            $wallet->send_tx_hash = '';
            $wallet->save();

            $transaction = $trRepository->store([
                'wallet_id' => $wallet->id,
                'recipient_id' => $wallet->recipient_id,
                'tx_id' => $txData['transaction_id'],
                'from_address' => $txData['from'],
                'tx_data' => $txData,
                'amount' => $amount,
            ]);

            // Auto-start the check only for "incoming" (buy) operations and if the transaction exceeds the threshold
            if ($transaction && $wallet->isOperationBuy() && $transaction->isAmlCheck()) {
                app(WalletService::class)->runAmlTransaction($transaction, 0);
            }
        }

        if ($wallet->isAddTransaction() && !$wallet->amount_trx) {
            ActivateWallet::dispatch($wallet->id); //dispatch
        }

        $this->runAutoSendForSell($wallet);
    }

    public function syncTransactionWebHook(Wallet $wallet, array $data): void
    {
        if (($data['network'] ?? '') !== 'tron' || ($data['currency'] ?? '') !== 'USDT') {
            return;
        }

        if (($data['direction'] ?? '') !== 'IN') {
            return;
        }

        $txId = $data['txid'] ?? '';

        $trRepository = new TransactionRepository();
        if (!$txId || $trRepository->getByTxId($txId)) {
            return;
        }

        $amount = (float)($data['amount'] ?? 0);

        $minTrAmount = Setting::getMinTransactionAmount();

        if ($amount < $minTrAmount) {
            $wallet->comment = 'Transaction below the minimum allowed amount detected (likely dust)';
            $wallet->save();
            return;
        }

        $wallet->status = WalletStatuses::ADD_TRANSACTION->value;
        $wallet->amount += $amount;
        $wallet->send_tx_hash = '';
        $wallet->save();

        $transaction = $trRepository->store([
            'wallet_id' => $wallet->id,
            'recipient_id' => $wallet->recipient_id,
            'tx_id' => $txId,
            'from_address' => $data['from'] ?? '',
            'tx_data' => $data,
            'amount' => $amount,
        ]);

        // Auto-start the check only for "incoming" (buy) operations
        if ($transaction && $wallet->isOperationBuy() && $transaction->isAmlCheck()) {
            app(WalletService::class)->runAmlTransaction($transaction, 0);
        }

        if ($wallet->isAddTransaction() && !$wallet->amount_trx) {
            ActivateWallet::dispatch($wallet->id);
        }

        $this->runAutoSendForSell($wallet);
    }

    /**
     * Automatic sending of funds for "payout" (sell) operations.
     * Works only when Setting::AUTO_SEND_SELL_ENABLED is enabled (disabled by default).
     *
     * @param Wallet $wallet
     * @return void
     */
    private function runAutoSendForSell(Wallet $wallet): void
    {
        return;


        if (!$wallet->isOperationSell() || !Setting::isAutoSendSellEnabled()) {
            return;
        }

        if (!app(WalletService::class)->isReadyForAutoSendSell($wallet)) {
            return;
        }

        // Give the wallet time to activate (TRX) before sending
        AutoSendSellWallet::dispatch($wallet->id)
            ->delay(now()->addMinutes(Development::isLocal() ? 0 : 1));
    }


}
