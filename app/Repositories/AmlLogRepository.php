<?php

namespace App\Repositories;

use App\Models\Wallet\AmlLog;
use App\Models\Wallet\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class AmlLogRepository extends Repository
{
    /**
     * @return Builder|AmlLog
     */
    protected function query(): Builder
    {
        return AmlLog::query();
    }

    public function getByWallet(int $walletId): Collection
    {
        return $this->query()
            ->where('wallet_id', $walletId)
            ->get();
    }

    public function store(array $result, int $walletId, int $serviceId, int $transactionId = null): ?AmlLog
    {
        $data = [
            'wallet_id' => $walletId,
            'aml_service_id' => $serviceId,
            'transaction_id' => $transactionId,
            'response' => $result,
            'aml_balance' => $result['balance'] ?? 0,
        ];

        try {
            return $this->query()->create($data);
        } catch (\Exception $exception) {
            Log::error('Failed to write amllog ', $data + [
                    'exception' => $exception->getMessage()
                ]);
        }

        return null;
    }
}
