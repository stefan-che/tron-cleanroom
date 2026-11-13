<?php

namespace App\Repositories\Recipient;

use App\Models\Recipient\Wallet;
use App\Repositories\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class RecipientWalletRepository extends Repository
{

    /**
     * @return Builder|Wallet
     */
    public function query(): Builder
    {
        return Wallet::query();
    }

    public function getByAddress(string $address): ?Wallet
    {
        return $this->query()
            ->where('address', $address)
            ->first();
    }

    public function getRecipientWalletByType(int $recipientId, int $type): ?Wallet
    {
        $wallets = $this->query()
            ->where('recipient_id', $recipientId)
            ->where('type', $type)
            ->get();

        return $wallets->count() ? $wallets->first() : null; // ->random(1)
    }

    /**
     * @param array $data
     * @return Wallet|null
     */
    public function store(array $data): ?Wallet
    {
        $data = [
            'address' => $data['address'],
            'recipient_id' => $data['recipient_id'],
            'blockchain_type' => $data['blockchain_type'],
            'type' => $data['type'],
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
            'address' => $data['address'] ?? $wallet->address,
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

}
