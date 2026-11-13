<?php

namespace App\Services\Aml\AmlBitOk\Transfers;

use App\Services\Aml\AmlBitOk\Aml;

class Risks
{
    public function getRisks(string $id): array
    {
        $runner = new Aml(sprintf('/v1/transfers/%s/risks/', $id));
        return $runner->execute();
    }

    private function getLocalData(): array
    {
        return [
            [
                "risk_level" => "medium",
                "occurred_at" => "2025-02-13T12:42:48+03:00",
                "detected_at" => "2025-02-24T20:05:38.138143+03:00",
                "risk_type" => "origin_of_funds",
                "entity_category" => "sanctions",
                "proximity" => "indirect",
                "value_in_fiat" => 15.14,
                "value_share" => 0.0338,
                "rule" => [
                    "rule_type" => "origin_of_funds",
                    "rule_sub_type" => "indirect",
                    "entity_category" => "sanctions",
                    "min_value_in_fiat" => 10.0,
                    "min_value_share" => 0.01,
                ],
                "fiat_currency" => "USD"
            ],
            [
                "risk_level" => "medium",
                "occurred_at" => "2025-02-13T12:42:48+03:00",
                "detected_at" => "2025-02-24T20:05:38.138193+03:00",
                "risk_type" => "origin_of_funds",
                "entity_category" => "gambling",
                "proximity" => "indirect",
                "value_in_fiat" => 12.77,
                "value_share" => 0.0285,
                "rule" => [
                    "rule_type" => "origin_of_funds",
                    "rule_sub_type" => "indirect",
                    "entity_category" => "gambling",
                    "min_value_in_fiat" => 10.0,
                    "min_value_share" => 0.01,
                ],
                "fiat_currency" => "USD"
            ],


            [
                "risk_level" => "medium",
                "occurred_at" => "2025-02-13T12:42:48+03:00",
                "detected_at" => "2025-03-08T18:52:35.966146+03:00",
                "risk_type" => "sender_exposure",
                "entity_category" => "sanctions",
                "proximity" => "indirect",
                "value_in_fiat" => null,
                "value_share" => 0.04,
                "rule" => [
                    "rule_type" => "address_exposure",
                    "rule_sub_type" => "all",
                    "entity_category" => "sanctions",
                    "min_value_in_fiat" => null,
                    "min_value_share" => 0.01,
                ],
                "fiat_currency" => "USD"
            ],
            [
                "risk_level" => "medium",
                "occurred_at" => "2025-02-13T12:42:48+03:00",
                "detected_at" => "2025-03-08T18:52:35.966247+03:00",
                "risk_type" => "sender_exposure",
                "entity_category" => "gambling",
                "proximity" => "indirect",
                "value_in_fiat" => null,
                "value_share" => 0.0264,
                "rule" => [
                    "rule_type" => "address_exposure",
                    "rule_sub_type" => "sender",
                    "entity_category" => "gambling",
                    "min_value_in_fiat" => null,
                    "min_value_share" => 0.01,
                ],
                "fiat_currency" => "USD",
            ],

            [
                "risk_level" => "medium",
                "occurred_at" => "2025-02-13T12:42:48+03:00",
                "detected_at" => "2025-03-08T18:52:35.966318+03:00",
                "risk_type" => "sender_exposure",
                "entity_category" => "bridge",
                "proximity" => "indirect",
                "value_in_fiat" => null,
                "value_share" => 0.0114,
                "rule" => [
                    "rule_type" => "address_exposure",
                    "rule_sub_type" => "sender",
                    "entity_category" => "bridge",
                    "min_value_in_fiat" => null,
                    "min_value_share" => 0.01,
                ],
                "fiat_currency" => "USD",
            ]

        ];
    }

}
