<?php

namespace App\Services\Aml\AmlBitOk\ManualCheck;

use App\Services\Aml\AmlBitOk\Aml;
use App\Helpers\Development;

class Result
{
    public function getResult(string $id): array
    {
        if (Development::isLocal() || Development::isTesting()) {
            return $this->getLocalData();
        }

        $runner = new Aml(sprintf('/v1/manual-checks/%s/', $id));
        return $runner->execute();
    }

    private function getLocalData(): array
    {
        return [
            "id" => "00000000-0000-0000-0000-000000000006",
            "created_at" => "2025-08-21T19:00:19.645115+03:00",
            "check_type" => "single_address",
            "check_status" => "checked",
            "checked_at" => "2025-08-21T19:00:22.051809+03:00",
            "transfer" => null,
            "address" => [
                "network" => "TRX",
                "address" => "TEXAMPLEADDRESS0000000000000000003",
            ],
            "risk_level" => "medium",
            "risk_score" => 0.53875,
            "fiat_currency" => "USD",
        ];
    }
}
