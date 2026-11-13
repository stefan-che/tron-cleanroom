<?php

namespace App\Repositories;

use App\Models\Setting;
use App\Models\WalletTrx;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletTrxRepository extends Repository
{

    /**
     * @return Builder|WalletTrx
     */
    public function query(): Builder
    {
        return WalletTrx::query();
    }

    public function getById(int $id): ?WalletTrx
    {
        return $this->query()
            ->where('id', $id)
            ->first();
    }

    public function getByAddress(string $address): ?WalletTrx
    {
        return $this->query()
            ->where('address', $address)
            ->first();
    }

    /**
     * @param array $data
     * @return WalletTrx|null
     */
    public function store(array $data): ?WalletTrx
    {
        $data = [
            'address' => $data['address'],
            'private_key' => $data['private_key'],
            'comment' => $data['comment'] ?? '',
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

    public function update(WalletTrx $wallet, array $data): bool
    {
        $data = [
            'amount' => $data['amount'] ?? $wallet->amount,
            'comment' => $data['comment'] ?? $wallet->comment,
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

    public function getRandomWallet(): ?WalletTrx
    {
        $amount = Setting::getCountTrxForActivateWallet();

        $wallets = $this->query()
            ->where('amount', '>' , $amount)
            ->get();

        return $wallets->count() ? $wallets->random(1)->first() : null;
    }

    public function getSumAllWallet(): float
    {
        $amount = Setting::getCountTrxForActivateWallet();

        return $this->query()
            ->where('amount', '>' , $amount)
            ->sum('amount');
    }
}
