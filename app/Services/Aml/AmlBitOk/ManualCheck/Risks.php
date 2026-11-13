<?php

namespace App\Services\Aml\AmlBitOk\ManualCheck;

use App\Helpers\Development;
use App\Services\Aml\AmlBitOk\Aml;

class Risks
{
    public function getRisks(string $id): array
    {
        if (Development::isLocal() || Development::isTesting()) {
            return $this->getLocalData();
        }

        $runner = new Aml(sprintf('/v1/manual-checks/%s/risks/', $id));
        return $runner->execute();
    }

    private function getLocalData(): array
    {
        return [
            [
                "risk_level" => "low",
                "occurred_at" => "2026-04-01T12:08:39.680285+03:00",
                "detected_at" => "2026-04-01T12:08:41.155429+03:00",
                "risk_type" => "address_exposure",
                "entity_category" => "high_risk_exchange",
                "proximity" => "indirect",
                "value_in_fiat" => null,
                "value_share" => 0.0237,
                "rule" => [
                    "rule_type" => "address_exposure",
                    "rule_sub_type" => "single_address",
                    "entity_category" => "high_risk_exchange",
                    "min_value_in_fiat" => null,
                    "min_value_share" => 0.01,
                ],
                "fiat_currency" => "USD",
            ],
            [
                "risk_level" => "medium",
                "occurred_at" => "2026-04-01T12:08:39.680285+03:00",
                "detected_at" => "2026-04-01T12:08:41.155589+03:00",
                "risk_type" => "address_exposure",
                "entity_category" => "sanctions",
                "proximity" => "indirect",
                "value_in_fiat" => null,
                "value_share" => 0.0149,
                "rule" => [
                    "rule_type" => "address_exposure",
                    "rule_sub_type" => "all",
                    "entity_category" => "sanctions",
                    "min_value_in_fiat" => null,
                    "min_value_share" => 0.01,
                ],
                "fiat_currency" => "USD",
            ]

        ];
    }

}
