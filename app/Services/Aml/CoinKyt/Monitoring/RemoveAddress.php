<?php

namespace App\Services\Aml\CoinKyt\Monitoring;

use App\Helpers\Development;
use App\Services\Aml\CoinKyt\Aml;

/**
 * 6.4. Disable monitoring - stop tracking an address.
 * DELETE /openapi/v1/monitoring/address?blockchain={btc|eth|trx}&address={address}
 *
 * Example response:
 * { "blockchain": "btc", "address": "...", "tokens": [""], "risk_profile": "...", "risk_profile_id": 2, "address_changes": true }
 */
class RemoveAddress
{
    public function remove(string $address, string $blockchain): array
    {
        if (Development::isLocal() || Development::isTesting()) {
            return $this->getLocalData($address, $blockchain);
        }

        return (new Aml('/monitoring/address', [
            'blockchain' => $blockchain,
            'address'    => $address,
        ], 'delete'))->execute();
    }

    private function getLocalData(string $address, string $blockchain): array
    {
        return [
            'blockchain'      => $blockchain,
            'address'         => $address,
            'tokens'          => [''],
            'risk_profile'    => 'local',
            'risk_profile_id' => 2,
            'address_changes' => true,
        ];
    }
}
