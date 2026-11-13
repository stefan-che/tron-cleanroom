<?php

namespace App\Services\Aml\AmlBitOk\ManualCheck;

use App\Helpers\Development;
use App\Services\Aml\AmlBitOk\Aml;


class CheckAddress
{

    public function checkAddress(
        string $address,
        string $network = 'TRX',
        string $tokenId = '',

    ): array
    {
        if (Development::isLocal() || Development::isTesting()) {
            return self::getLocalData();
        }

        $runner = new Aml('/v1/manual-checks/check-address/', [
            'address' => $address,
            'token_id' => $tokenId,
            'network' => $network,
        ], [], 'POST');
        return $runner->post();
    }

    private static function getLocalData(): array
    {
        return [
            "id" => "00000000-0000-0000-0000-000000000008",
            "created_at" => "2026-04-01T12:08:39.680285+03:00",
            "check_type" => "single_address",
            "check_status" => "checking",
            "checked_at" => null,
            "transfer" => null,
            "address" => [
                "network" => "TRX",
                "address" => "TEXAMPLEADDRESS0000000000000000003",
            ],
            "risk_level" => "undefined",
            "risk_score" => null,
            "fiat_currency" => "USD",
        ];
    }

}
