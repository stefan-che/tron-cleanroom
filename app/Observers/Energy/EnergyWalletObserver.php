<?php

namespace App\Observers\Energy;

use App\Enums\Energy\EnergyWalletEvent;
use App\Jobs\Energy\SmartEnergyJob;
use App\Models\Energy\EnergyWallet;
use Illuminate\Support\Facades\Log;

class EnergyWalletObserver
{
    public function created(EnergyWallet $wallet): void
    {
        Log::channel('energy')->info('EnergyWalletObserver created', [
            'wallet_id' => $wallet->id,
            'auto_replenishment_enabled' => $wallet->auto_replenishment_enabled,
        ]);

        if (!$wallet->auto_replenishment_enabled) {
            return;
        }

        SmartEnergyJob::dispatch($wallet->id, EnergyWalletEvent::SMART_START, 'system', 'start on add');
    }

    public function updated(EnergyWallet $wallet): void
    {
        Log::channel('energy')->info('EnergyWalletObserver updated', [
            'wallet_id' => $wallet->id,
            'auto_replenishment_enabled' => $wallet->auto_replenishment_enabled,
        ]);

        if ($wallet->wasChanged('auto_replenishment_enabled')) {
            if ($wallet->auto_replenishment_enabled) {
                SmartEnergyJob::dispatch($wallet->id, EnergyWalletEvent::SMART_START, 'system', 'start on enable');
            } else {
                SmartEnergyJob::dispatch($wallet->id, EnergyWalletEvent::SMART_STOP, 'system', 'stop on disable');
            }
        }
    }
}
