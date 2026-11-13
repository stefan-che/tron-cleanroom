<?php

namespace App\Services\Aml\AmlBitOk\Transfers;

use App\Services\Aml\AmlBitOk\Aml;

class RecheckCounterparty
{
    public function recheck(
        string $id,
    ): array
    {
        $runner = new Aml(sprintf('/v1/transfers/%s/recheck-counterparty/', $id), [
        ], [], 'POST');
        return $runner->post();
    }

    private function getLocalData(): array
    {
        return [
            'id' => '00000000-0000-0000-0000-000000000010',
            'client_id' => null,
            'attempt_id' => null,
            'registered_at' => '2025-02-24T20:05:37.550220+03:00',
            'risk_level' => 'medium',
            'risk_score' => 0.560189,
            'network' => 'TRX',
            'token_id' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            'token_symbol' => 'USDT',
            'tx_status' => 'bound',
            'tx_hash' => '0000000000000000000000000000000000000000000000000000000000000003',
            'occurred_at' => '2025-02-13T09:42:48Z',
            'input_address' => 'TEXAMPLEADDRESS0000000000000000007',
            'output_address' => 'TEXAMPLEADDRESS0000000000000000003',
            'direction' => 'incoming',
            'amount' => 448.0,
            'value_in_fiat' => 448.01,
            'check_state' => [
                'exposure' => 'checked',
                'exposure_checked_at' => '2025-02-24T17:05:38.097086Z',
                'counterparty' => 'checking',
                'counterparty_checked_at' => null,
                'sanctions' => 'none',
                'sanctions_checked_at' => null,
            ],
            'fiat_currency' => 'USD',
        ];
    }

}
