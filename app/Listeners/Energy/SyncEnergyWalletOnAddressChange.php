<?php

namespace App\Listeners\Energy;

use App\Enums\Energy\EnergyWalletEvent;
use App\Events\Energy\RecipientWalletAddressChanged;
use App\Repositories\Energy\EnergyWalletRepository;
use Illuminate\Support\Facades\Log;

readonly class SyncEnergyWalletOnAddressChange
{
    public function __construct(private EnergyWalletRepository $repository){}

    public function handle(RecipientWalletAddressChanged $event): void
    {
        $existing = $this->repository->findByAddressOrRecipientWallet(
            $event->oldAddress,
            $event->wallet->id,
        );

        if (!$existing) {
            return;
        }

        // The old record is NOT touched; the user disables it manually when needed.
        // Create a new record for the new address with the same enabled state.
        $newWallet = $this->repository->create([
            'recipient_id' => $event->wallet->recipient_id,
            'recipient_wallet_id' => $event->wallet->id,
            'address' => $event->newAddress,
            'label' => $existing->label,
            'auto_replenishment_enabled' => $existing->auto_replenishment_enabled,
            'telegram_chat_id' => $event->changedById,
        ], $event->changedBy);

        // Also record the address-change context
        $newWallet->addHistory(
            EnergyWalletEvent::ADDRESS_CHANGED,
            $event->changedBy,
            "Created on address change: {$event->oldAddress} → {$event->newAddress}",
        );

        Log::channel('energy')->info('EnergyWallet created for new address', [
            'recipient_wallet_id' => $event->wallet->id,
            'telegram_chat_id' => $event->changedById,
            'old_energy_wallet_id' => $existing->id,
            'auto_replenishment_enabled' => $existing->auto_replenishment_enabled,
            'new_energy_wallet_id' => $newWallet->id,
            'old_address' => $event->oldAddress,
            'new_address' => $event->newAddress,
        ]);
    }
}
