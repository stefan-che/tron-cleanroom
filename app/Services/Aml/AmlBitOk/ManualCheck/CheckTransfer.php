<?php

namespace App\Services\Aml\AmlBitOk\ManualCheck;

use App\Helpers\Development;
use App\Services\Aml\AmlBitOk\Aml;


class CheckTransfer
{
    public function checkTransfer(
        string $txHash,
        string $tokenId,
        string $outputAddress,
        string $direction = 'incoming',
        string $network = 'TRX',
    ): array
    {
        if (Development::isLocal() || Development::isTesting()) {
            return self::getLocalData();
        }

        $runner = new Aml('/v1/manual-checks/check-transfer/', [
            'direction' => $direction,
            'network' => $network,
            'tx_hash' => $txHash,
            'token_id' => $tokenId,
            'output_address' => $outputAddress,

        ], [], 'POST');
        return $runner->post();
    }

    private static function getLocalData(): array
    {
        return [
            "id" => "00000000-0000-0000-0000-000000000007",
            "created_at" => "2026-04-01T17:39:05.967479+03:00",
            "check_type" => "deposit",
            "check_status" => "checking",
            "checked_at" => null,
            "transfer" => [
                "network" => "TRX",
                "token_id" => "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t",
                "token_symbol" => "USDT",
                "tx_status" => "bound",
                "tx_hash" => "0000000000000000000000000000000000000000000000000000000000000002",
                "occurred_at" => "2026-03-03T15:47:03+03:00",
                "input_address" => "TEXAMPLEADDRESS0000000000000000005",
                "output_address" => "TEXAMPLEADDRESS0000000000000000006",
                "direction" => "incoming",
                "amount" => 5840.0,
                "value_in_fiat" => 5840.20790386,
            ],
            "address" => null,
            "risk_level" => "undefined",
            "risk_score" => null,
            "fiat_currency" => "USD",
        ];
    }

}
