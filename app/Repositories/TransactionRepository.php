<?php

namespace App\Repositories;

use App\Enums\TransactionStatuses;
use App\Models\Wallet\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class TransactionRepository extends Repository
{

    /**
     * @return Builder|Transaction
     */
    public function query(): Builder
    {
        return Transaction::query();
    }

    public function getById(int $id): ?Transaction
    {
        return $this->query()
            ->where('id', $id)
            ->first();
    }

    public function getByTxId(string $hash): ?Transaction
    {
        return $this->query()
            ->where('tx_id', trim($hash))
            ->first();
    }

    /**
     * @param array $data
     * @return Transaction|null
     */
    public function store(array $data): ?Transaction
    {
        $data = [
            'status' => $data['status'] ?? TransactionStatuses::NEW->value,
            'wallet_id' => $data['wallet_id'],
            'recipient_id' => $data['recipient_id'],
            'tx_id' => $data['tx_id'],
            'tx_data' => $data['tx_data'],
            'amount' => $data['amount'],
            'from_address' => $data['from_address'],
        ];

        try {
            return $this->query()->create($data);
        } catch (\Exception $exception) {
            Log::error('Failed to save transaction', $data + [
                    'exception' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ]);
        }

        return null;
    }

    /**
     * @param Transaction $transaction
     * @param array $data
     * @return bool
     */
    public function update(Transaction $transaction, array $data): bool
    {
        $data = [
            'status' => $data['status'] ?? $transaction->status,
            'aml_risk_score' => array_key_exists('aml_risk_score', $data) ? $data['aml_risk_score'] : $transaction->aml_risk_score,
            'aml_file_report_link' => array_key_exists('aml_file_report_link', $data) ? $data['aml_file_report_link'] : $transaction->aml_file_report_link,
            'aml_uid' => array_key_exists('aml_uid', $data) ? $data['aml_uid'] : $transaction->aml_uid,
        ];

        try {
            return $transaction->update($data);
        } catch (\Exception $exception) {
            Log::error('Failed to update transaction', $data + [
                    'exception' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                    'transaction' => $transaction->id,
                ]);
        }

        return false;
    }

    /**
     * @param Transaction $transaction
     * @param float $riscScore
     * @param string|null $report
     * @return void
     */
    public function updateAmlRiskScore(Transaction $transaction, float $riscScore, ?string $report): void
    {
        $this->update($transaction, [
            'aml_risk_score' => $riscScore,
            'aml_file_report_link' => (string)$report,
        ]);

        app(WalletRepository::class)->update($transaction->wallet, [
            'aml_risk_score' => $riscScore,
            'aml_file_report_link' => (string)$report,
        ]);
    }
}
