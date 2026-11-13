<?php

namespace App\Repositories\Aml;

use App\Enums\AmlAddressStatuses;
use App\Models\Aml\AmlAddress;
use App\Models\Recipient\Wallet;
use App\Repositories\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class AmlAddressRepository extends Repository
{
    /**
     * @return Builder|AmlAddress
     */
    protected function query(): Builder
    {
        return AmlAddress::query();
    }


    public function getById(int $id): ?AmlAddress
    {
        return $this->query()
            ->where('id', $id)
            ->first();
    }

    public function getByAddress(string $address): ?AmlAddress
    {
        return $this->query()
            ->where('address', $address)
            ->first();
    }

    public function getByBlockChainAddress(string $address, ?string $blockchain): ?AmlAddress
    {
        return $this->query()
            ->where('address', $address)
            ->when($blockchain, function (Builder $query) use ($blockchain) {
                $query->where('blockchain_type', AmlAddress::getBlockChainTypeByName($blockchain));
            })
            ->first();
    }

    public function getByAddressInProcessed(string $address): ?AmlAddress
    {
        return $this->query()
            ->where('address', $address)
            ->where('status', AmlAddressStatuses::PROCESS->value)
            ->first();
    }

    public function store(array $data): ?AmlAddress
    {
        $data = [
            'address' => $data['address'],
            'blockchain_type' => $data['blockchain_type'] ?? Wallet::BLOCKCHAIN_TYPE_TRC_20,
            'status' => $data['status'] ?? AmlAddressStatuses::NEW->value,
            'comment' => $data['comment'] ?? '',
            'current_tx_count' => $data['current_tx_count'] ?? 0,
            'last_check_tx_count' => $data['last_check_tx_count'] ?? 0,
            'current_transaction_hash' => $data['current_transaction_hash'] ?? '',
            'last_check_transaction_hash' => $data['last_check_transaction_hash'] ?? '',
            'current_trx_transaction_hash' => $data['current_trx_transaction_hash'] ?? '',
            'last_check_trx_transaction_hash' => $data['last_check_trx_transaction_hash'] ?? '',
            'is_test' => $data['is_test'] ?? false,
        ];

        try {
            return $this->query()->create($data);
        } catch (\Exception $exception) {
            Log::error('Failed to add amlAddress ', $data + [
                    'exception' => $exception->getMessage()
                ]);
        }

        return null;
    }

    public function update(AmlAddress $amlAddress, array $data): bool
    {
        $data = [
            'status' => $data['status'] ?? $amlAddress->status,
            'aml_risk_score' => $data['aml_risk_score'] ?? $amlAddress->aml_risk_score,
            'aml_file_report_link' => $data['aml_file_report_link'] ?? $amlAddress->aml_file_report_link,
            'comment' => $data['comment'] ?? $amlAddress->comment,
            'has_error' => $data['has_error'] ?? $amlAddress->has_error,
            'response' => $data['response'] ?? $amlAddress->response,
            'error' => $data['error'] ?? $amlAddress->error,
            'check_finish_at' => $data['check_finish_at'] ?? $amlAddress->check_finish_at,
            'current_tx_count' => $data['current_tx_count'] ?? $amlAddress->current_tx_count,
            'last_check_tx_count' => $data['last_check_tx_count'] ?? $amlAddress->last_check_tx_count,
            'current_transaction_hash' => $data['current_transaction_hash'] ?? $amlAddress->current_transaction_hash,
            'last_check_transaction_hash' => $data['last_check_transaction_hash'] ?? $amlAddress->last_check_transaction_hash,
            'current_trx_transaction_hash' => $data['current_trx_transaction_hash'] ?? $amlAddress->current_trx_transaction_hash,
            'last_check_trx_transaction_hash' => $data['last_check_trx_transaction_hash'] ?? $amlAddress->last_check_trx_transaction_hash,
            'aml_uid' => array_key_exists('aml_uid', $data) ? $data['aml_uid'] : $amlAddress->aml_uid,
            'blockchain_type' => $data['blockchain_type'] ?? $amlAddress->blockchain_type,
            'is_test' => array_key_exists('is_test', $data) ? $data['is_test'] : $amlAddress->is_test,
            'last_check_hash_at' => $data['last_check_hash_at'] ?? $amlAddress->last_check_hash_at,
            'aml_balance' => $data['aml_balance'] ?? $amlAddress->aml_balance,
        ];

        try {
            return $amlAddress->update($data);
        } catch (\Exception $exception) {
            Log::error('Failed to update amlAddress', $data + [
                    'exception' => $exception->getMessage(),
                    'amlAddress' => $amlAddress->id,
                ]);
        }

        return false;
    }

}
