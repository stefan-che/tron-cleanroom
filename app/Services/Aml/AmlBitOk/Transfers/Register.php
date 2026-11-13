<?php

namespace App\Services\Aml\AmlBitOk\Transfers;

use App\Services\Aml\AmlBitOk\Aml;
use App\Helpers\Development;

class Register
{
    public function register(
        string $txHash,
        string $tokenId,
        string $outputAddress,
        string $direction = 'incoming',
        string $network = 'TRX',
    ): array
    {

//        'client_id': 'id0001',
//'direction': 'incoming',
//'network': 'ETH',
//'tx_hash': '0x0000000000000000000000000000000000000000000000000000000000000004',
//'token_id': '0xdac17f958d2ee523a2206206994597c13d831ec7',
//'output_address': '0x0000000000000000000000000000000000000002'


        if (Development::isLocal() || Development::isTesting()) {
            return self::getLocalData();
        }

        $runner = new Aml('/v1/transfers/register/', [
            'direction' => $direction,
            'network' => $network,
            'tx_hash' => $txHash,
            'token_id' => $tokenId,
            'output_address' => $outputAddress,

        ], [], 'POST');

        return $runner->post();
    }

    public static function getLocalData(): array
    {
        return [
            'id' => '00000000-0000-0000-0000-000000000009',
            'client_id' => null,
            'attempt_id' => null,
            'registered_at' => '2026-04-01T15:30:37.269005+03:00',
            'risk_level' => 'undefined',
            'risk_score' => null,
            'network' => 'TRX',
            'token_id' => 'native',
            'token_symbol' => 'TRX',
            'tx_status' => 'binding',
            'tx_hash' => '0000000000000000000000000000000000000000000000000000000000000002',
            'occurred_at' => null,
            'input_address' => null,
            'output_address' => 'TEXAMPLEADDRESS0000000000000000006',
            'direction' => 'incoming',
            'amount' => null,
            'value_in_fiat' => null,
            'check_state' => [
                'exposure' => 'queued',
                'exposure_checked_at' => null,
                'counterparty' => 'none',
                'counterparty_checked_at' => null,
                'sanctions' => 'none',
                'sanctions_checked_at' => null,
            ],
            'fiat_currency' => 'USD'
        ];
    }

}
