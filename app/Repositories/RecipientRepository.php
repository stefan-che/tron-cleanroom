<?php

namespace App\Repositories;

use App\Models\Recipient\Recipient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class RecipientRepository extends Repository
{

    /**
     * @return Builder|Recipient
     */
    public function query(): Builder
    {
        return Recipient::query();
    }

    public function paginate(int $perPage = 30, bool $withWallets = false): LengthAwarePaginator
    {
        $perPage = max(1, min(30, $perPage));

        $query = $this->query()->latest('id');

        if ($withWallets) {
            $query->with(['wallets', 'ledgerAccounts.wallet']);
        }

        return $query->paginate($perPage);
    }

    public function getById(int $id, bool $withWallets = false): ?Recipient
    {
        $query = $this->query()->where('id', $id);

        if ($withWallets) {
            $query->with(['wallets', 'ledgerAccounts.wallet']);
        }

        return $query->first();
    }

    /**
     * Universal search: by ID (numeric), by external_id or by name (string)
     */
    public function findByIdentifier(string $identifier, bool $withWallets = false): ?Recipient
    {
        $identifier = substr(trim($identifier), 0 , 22);

        if (ctype_digit($identifier)) {
            return $this->getById((int)$identifier, $withWallets);
        }

        $query = $this->query()
            ->where('external_id', $identifier)
            ->orWhere('name', 'like', '%' . $identifier . '%');

        if ($withWallets) {
            $query->with(['wallets', 'ledgerAccounts.wallet']);
        }

        return $query->first();
    }

    public function getByExternalId(string $externalId): ?Recipient
    {
        return $this->query()
            ->where('external_id', $externalId)
            ->withTrashed()
            ->first();
    }

    /**
     * @param array $data
     * @return Recipient|null
     */
    public function store(array $data): ?Recipient
    {
        $data = [
            'name' => $data['name'],
            'external_id' => $data['external_id'] ?? null,
            'comment' => $data['comment'] ?? '',
        ];

        try {
            return $this->query()->create($data);
        } catch (\Exception $exception) {
            Log::error('Failed to save recipient', $data + [
                    'exception' => $exception->getMessage()
                ]);
        }

        return null;
    }

    public function update(Recipient $recipient, array $data): bool
    {
        $data = [
            'name' => $data['name'] ?? $recipient->name,
            'comment' => $data['comment'] ?? $recipient->comment,
            'deleted_at' => null,
        ];

        try {
            return $recipient->update($data);
        } catch (\Exception $exception) {
            Log::error('Failed to update recipient', $data + [
                    'exception' => $exception->getMessage(),
                    'recipient' => $recipient->id,
                ]);
        }

        return false;
    }
}
