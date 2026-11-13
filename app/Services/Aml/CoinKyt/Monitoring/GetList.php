<?php

namespace App\Services\Aml\CoinKyt\Monitoring;

use App\Helpers\Development;
use App\Services\Aml\CoinKyt\Aml;

/**
 * 6.5. List addresses under monitoring.
 * GET /openapi/v1/monitoring/list
 *
 * Example response:
 * [ { "blockchain": "btc", "address": "...", "tokens": [""], "risk_profile": "...", "risk_profile_id": 2, "address_changes": false }, ... ]
 */
class GetList
{
    public function get(): array
    {
        if (Development::isLocal() || Development::isTesting()) {
            return $this->getLocalData();
        }

        return (new Aml('/monitoring/list'))->execute();
    }

    private function getLocalData(): array
    {
        return [
            [
                'blockchain'      => 'trx',
                'address'         => 'TEXAMPLEADDRESS0000000000000000003',
                'tokens'          => [''],
                'risk_profile'    => 'local',
                'risk_profile_id' => 2,
                'address_changes' => true,
            ],
        ];
    }
}
