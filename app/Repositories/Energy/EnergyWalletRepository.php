<?php

namespace App\Repositories\Energy;

use App\Enums\Energy\EnergyWalletEvent;
use App\Jobs\Energy\SmartEnergyJob;
use App\Models\Energy\EnergyWallet;
use App\Models\Recipient\Wallet as RecipientWallet;
use App\Repositories\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class EnergyWalletRepository extends Repository
{

    public function query(): Builder
    {
        return EnergyWallet::query();
    }

    public function total(bool $onlyEnabled = false, ?array $recipientIds = null, ?int $chatId = null, bool $withoutRecipient = false): int
    {
        return $this->applyRecipientFilter(
            $this->query()
                ->when($onlyEnabled, fn($q) => $q->where('auto_replenishment_enabled', true))
                ->when($withoutRecipient, fn($q) => $q->whereNull('recipient_wallet_id')),
            $recipientIds,
            $chatId
        )->count();
    }

    public function getPage(int $page, int $perPage, bool $onlyEnabled = false, ?array $recipientIds = null, ?int $chatId = null, bool $withoutRecipient = false): Collection
    {
        return $this->applyRecipientFilter(
            $this->query()
                ->with('recipient')
                ->when($onlyEnabled, fn($q) => $q->where('auto_replenishment_enabled', true))
                ->when($withoutRecipient, fn($q) => $q->whereNull('recipient_wallet_id')),
            $recipientIds,
            $chatId
        )->latest()
            ->skip(($page - 1) * $perPage)
            ->take($perPage)->get();
    }

    public function find(int $id): ?EnergyWallet
    {
        return $this->query()->find($id);
    }

    public function findWithRecipient(int $id): ?EnergyWallet
    {
        return $this->query()->with('recipient')->find($id);
    }

    /**
     * Finds a pool record by address or by the linked recipient wallet.
     * Used when showing the recipient wallet card and when changing the address.
     */
    public function findByAddressOrRecipientWallet(string $address, int $recipientWalletId): ?EnergyWallet
    {
        return $this->query()
            ->where('address', $address)
            ->orWhere('recipient_wallet_id', $recipientWalletId)
            ->latest()
            ->first();
    }

    public function existsByAddress(string $address): bool
    {
        return $this->query()->where('address', $address)->withTrashed()->exists();
    }

    public function countExpired(?array $recipientIds = null): int
    {
        return $this->applyRecipientFilter(
            $this->query()
                ->where('auto_replenishment_enabled', true)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()),
            $recipientIds
        )->count();
    }

    public function getExpired(?array $recipientIds = null): Collection
    {
        return $this->applyRecipientFilter(
            $this->query()
                ->where('auto_replenishment_enabled', true)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->with('recipient')
                ->orderBy('expires_at'),
            $recipientIds
        )->get();
    }

    public function search(string $query, int $limit = 20, ?array $recipientIds = null, bool $withoutRecipient = false): Collection
    {
        return $this->applyRecipientFilter(
            $this->query()
                ->with('recipient')
                ->where(fn($q) => $q
                    ->where('address', 'like', '%' . $query . '%')
                    ->orWhere('label', 'like', '%' . $query . '%')
                )
                ->when($withoutRecipient, fn($q) => $q->whereNull('recipient_wallet_id'))
                ->latest()
                ->take($limit),
            $recipientIds
        )->get();
    }

    private function applyRecipientFilter(Builder $query, ?array $recipientIds, ?int $chatId = null): Builder
    {
        if ($recipientIds === null) {
            return $query;
        }

        if ($chatId !== null) {
            return $query->where(function ($q) use ($recipientIds, $chatId) {
                $q->orWhere('telegram_chat_id', $chatId);
                if (!empty($recipientIds)) {
                    $q->orWhereHas('recipientWallet', fn($q2) => $q2->whereIn('recipient_id', $recipientIds));
                }
            });
        }

        if (empty($recipientIds)) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereHas('recipientWallet', fn($q) => $q->whereIn('recipient_id', $recipientIds));
    }

    /**
     * Creates a pool record with arbitrary data and writes history.
     * If expires_at is not given and auto_replenishment_enabled = true, the default is set.
     */
    public function create(array $data, string $changedBy): EnergyWallet
    {
        if (($data['auto_replenishment_enabled'] ?? false) && !array_key_exists('expires_at', $data)) {
            $data['expires_at'] = now()->addDays(EnergyWallet::DEFAULT_EXPIRES_DAYS);
        }

        /** @var EnergyWallet $wallet */
        $wallet = $this->query()->create($data);
        $wallet->addHistory(EnergyWalletEvent::ADDED, $changedBy);

        if ($data['auto_replenishment_enabled'] ?? false) {
            $wallet->addHistory(EnergyWalletEvent::ENABLED, $changedBy);
        }

        return $wallet;
    }

    /**
     * Creates a pool record from an existing recipient wallet.
     * The label is built automatically from the recipient and the wallet type.
     */
    public function createFromRecipientWallet(RecipientWallet $recipientWallet, string $changedBy, int $changedById = 0): EnergyWallet
    {
        $recipientName = $recipientWallet->recipient?->name ?? '';
        $label = $recipientName ? "{$recipientName} / {$recipientWallet->type_name}" : $recipientWallet->type_name;

        return $this->create([
            'recipient_wallet_id' => $recipientWallet->id,
            'address' => $recipientWallet->address,
            'label' => $label,
            'auto_replenishment_enabled' => true,
            'telegram_chat_id' => $changedById,
            // expires_at is set automatically in create()
        ], $changedBy);
    }

    /**
     * Toggles the auto-replenishment flag and writes history.
     */
    public function toggle(EnergyWallet $wallet, string $changedBy): EnergyWallet
    {
        $newValue = !$wallet->auto_replenishment_enabled;
        $wallet->update(['auto_replenishment_enabled' => $newValue]);

        $event = $newValue ? EnergyWalletEvent::ENABLED : EnergyWalletEvent::DISABLED;
        $wallet->addHistory($event, $changedBy);

        return $wallet;
    }

    /**
     * Removes a record from the pool (soft delete) and writes history.
     * The API stop is queued; the job finds the wallet via withTrashed().
     */
    public function remove(EnergyWallet $wallet, string $changedBy): void
    {
        $wallet->addHistory(EnergyWalletEvent::REMOVED, $changedBy);
        $wallet->delete();

        SmartEnergyJob::dispatch($wallet->id, EnergyWalletEvent::SMART_STOP, 'system', 'stop on removal');
    }
}
