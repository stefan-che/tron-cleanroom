<?php

namespace App\Services\Aml\CoinKyt\Monitoring;

use App\Helpers\Development;
use App\Services\Aml\CoinKyt\Aml;

/**
 * 6.3. Configure monitoring - start tracking an address.
 * POST /openapi/v1/monitoring/address?blockchain={btc|eth|trx}&address={address}
 *      &risk_profile_id={id}&address_changes={true|false}&tokens={token}
 *
 * Example response:
 * {
 *   "details": "Address successfully added to monitoring",
 *   "data": { "blockchain": "btc", "address": "...", "tokens": [""], "risk_profile": "...", "risk_profile_id": 2, "address_changes": true }
 * }
 */
class AddAddress
{
    public function add(
        string $address,
        string $blockchain,
        int $riskProfileId,
        bool $addressChanges = true,
        ?string $token = null,
    ): array {
        if (Development::isLocal() || Development::isTesting()) {
            return $this->getLocalData($address, $blockchain, $riskProfileId, $addressChanges, $token);
        }

        $params = [
            'blockchain'      => $blockchain,
            'address'         => $address,
            'risk_profile_id' => $riskProfileId,
            'address_changes' => $addressChanges ? 'true' : 'false',
        ];

        if ($token !== null && $token !== '') {
            $params['tokens'] = $token;
        }

        return (new Aml('/monitoring/address', $params, 'post'))->execute();
    }

    private function getLocalData(
        string $address,
        string $blockchain,
        int $riskProfileId,
        bool $addressChanges,
        ?string $token,
    ): array {
        return [
            'details' => 'Address successfully added to monitoring',
            'data'    => [
                'blockchain'      => $blockchain,
                'address'         => $address,
                'tokens'          => [$token ?? ''],
                'risk_profile'    => 'local',
                'risk_profile_id' => $riskProfileId,
                'address_changes' => $addressChanges,
            ],
        ];
    }
}
