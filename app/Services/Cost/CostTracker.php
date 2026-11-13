<?php

namespace App\Services\Cost;

use App\Models\Wallet\Wallet;
use Illuminate\Support\Facades\Log;

class CostTracker
{
    /**
     * Adds the energy rental cost to the wallet (TRX).
     */
    public static function addEnergyCost(Wallet $wallet, float $costTrx): void
    {
        if ($costTrx <= 0) {
            return;
        }

        $wallet->increment('energy_cost_trx', $costTrx);

        Log::channel('energy')->info('CostTracker: energy cost added', [
            'wallet_id' => $wallet->id,
            'cost_trx' => $costTrx,
        ]);
    }

}