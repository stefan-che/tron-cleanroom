<?php

namespace App\Services\Aml\AmlBitOk\Transfers;

use App\Services\Aml\AmlBitOk\Aml;

class Exposure
{
    public function getExposure(string $id): array
    {
        $runner = new Aml(sprintf('/v1/transfers/%s/exposure/', $id));
        return $runner->execute();
    }

    private function getLocalData(): array
    {
        return [
            'check_status' => 'checked',
            'checked_at' => '2025-02-24T17:05:38.097086Z',
            'direct_interaction' => null,
            'indirect_interaction' => [
                [
                    'entity_category' => 'exchange',
                    'value_share' => 0.8897,
                    'value_in_fiat' => 398.594497,
                ],
                [
                    'entity_category' => 'sanctions',
                    'value_share' => 0.0338,
                    'value_in_fiat' => 15.142738,
                ],
                [
                    'entity_category' => 'gambling',
                    'value_share' => 0.0285,
                    'value_in_fiat' => 12.768285,
                ],
                [
                    'entity_category' => 'other',
                    'value_share' => 0.0178,
                    'value_in_fiat' => 7.974578,],
                [
                    'entity_category' => 'payment_service_provider',
                    'value_share' => 0.0159,
                    'value_in_fiat' => 7.123359,
                ],
                [
                    'entity_category' => 'high_risk_exchange',
                    'value_share' => 0.0047,
                    'value_in_fiat' => 2.105647,
                ],
                [
                    'entity_category' => 'custodial_wallet',
                    'value_share' => 0.003,
                    'value_in_fiat' => 1.34403
                ],
                [
                    'entity_category' => 'enforcement_action',
                    'value_share' => 0.0027,
                    'value_in_fiat' => 1.209627,
                ],
                [
                    'entity_category' => 'iaas',
                    'value_share' => 0.001,
                    'value_in_fiat' => 0.44801,
                ],
                [
                    'entity_category' => 'bridge',
                    'value_share' => 0.0006,
                    'value_in_fiat' => 0.268806,
                ],
                10 => [
                    'entity_category' => 'dust',
                    'value_share' => 0.0022,
                    'value_in_fiat' => 0.985622,
                ],
            ],
            'fiat_currency' => 'USD',
        ];
    }

}
