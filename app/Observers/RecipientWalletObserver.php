<?php

namespace App\Observers;

use App\Events\Energy\RecipientWalletAddressChanged;
use App\Models\Recipient\Wallet;
use App\Repositories\Energy\EnergyWalletRepository;

class RecipientWalletObserver
{
    public function created(Wallet $wallet): void
    {
        if ($wallet->blockchain_type !== Wallet::BLOCKCHAIN_TYPE_TRC_20) {
            return;
        }

        $changedBy = $wallet->changedBy !== 'system'
            ? $wallet->changedBy
            : (auth()->check() ? (auth()->user()->name ?? auth()->user()->email) : 'system');

        app(EnergyWalletRepository::class)->createFromRecipientWallet($wallet, $changedBy, $wallet->changedById);
    }

    public function updated(Wallet $wallet): void
    {
        if (!$wallet->wasChanged('address')) {
            return;
        }

        $changedBy = $wallet->changedBy !== 'system'
            ? $wallet->changedBy
            : (auth()->check() ? (auth()->user()->name ?? auth()->user()->email) : 'admin');

        event(new RecipientWalletAddressChanged(
            wallet:     $wallet,
            oldAddress: $wallet->getOriginal('address'),
            newAddress: $wallet->address,
            changedBy:  $changedBy,
            changedById:  $wallet->changedById,
        ));
    }
}
