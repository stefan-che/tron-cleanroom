<?php

namespace App\Services\Aml\CoinKyt;

use App\Helpers\Development;

/**
 * AML address check.
 * GET /openapi/v1/address?blockchain={blockchain}&address={address}
 */
class CheckAddress
{

    public function check(string $address, string $blockchain): array
    {
        if (Development::isLocal() || Development::isTesting()) {
            return $this->getLocalData();
        }

        return (new Aml('/address', [
            'blockchain' => $blockchain,
            'address' => $address,
        ]))->execute();
    }

    private function getLocalData(): array
    {
        return [
            "name" => null,
            "type_label" => null,
            "type" => null,
            "id" => "00000000-0000-0000-0000-000000000004",
            "blockchain" => "trx",
            "token" => "",
            "address" => "TEXAMPLEADDRESS0000000000000000003",
            "risk_score" => "0.39061444686276736",
            "risk_score_grade" => "moderate",
            "directs" => [],
            "indirects" => [
                0 => [
                    "type_label" => "Licensed exchange service",
                    "risk_score_grade" => "low",
                    "type" => "EXCHANGE_LICENSED",
                    "total_count" => "83.55507488241794429688478546",
                    "total_count_coef" => "0.8355507488241794429688478546",
                    "total" => "11386.299670",
                ],
                [
                    "type_label" => "Rewards/Fees",
                    "risk_score_grade" => "low",
                    "type" => "REWARDS_FEES",
                    "total_count" => "12.94270675453242268774613429",
                    "total_count_coef" => "0.1294270675453242268774613429",
                    "total" => "1763.741315",
                ],
                [
                    "type_label" => "Undefined",
                    "risk_score_grade" => "undefined",
                    "type" => "UNDEFINED",
                    "total_count" => "1.861511050469122965607159511",
                    "total_count_coef" => "0.01861511050469122965607159511",
                    "total" => "253.673672",
                ],
                [
                    "type_label" => "Decentralized exchange",
                    "risk_score_grade" => "moderate",
                    "type" => "DECENTRALIZED_EXCHANGE",
                    "total_count" => "1.250822868142150124128811717",
                    "total_count_coef" => "0.01250822868142150124128811717",
                    "total" => "170.453369",
                ],
                [
                    "type_label" => "Unlicensed exchange service",
                    "risk_score_grade" => "moderate",
                    "type" => "EXCHANGE_UNLICENSED",
                    "total_count" => "0.2948325104980174492019634262",
                    "total_count_coef" => "0.002948325104980174492019634262",
                    "total" => "40.177707",
                ],
                [
                    "type_label" => "Other",
                    "risk_score_grade" => "undefined",
                    "type" => "OTHER",
                    "total_count" => "0.06203335054734894568038245100",
                    "total_count_coef" => "0.00062033350547348945680382451",
                    "total" => "8.453470",
                ],
                [
                    "type_label" => "Sanctions",
                    "risk_score_grade" => "high",
                    "type" => "SANCTIONS",
                    "total_count" => "0.01353434040485874300137086655",
                    "total_count_coef" => "0.0001353434040485874300137086655",
                    "total" => "1.844365",
                ],
                [
                    "type_label" => "Online marketplace",
                    "risk_score_grade" => "low",
                    "type" => "ONLINE_MARKETPLACE",
                    "total_count" => "0.008814366093858931254673901350",
                    "total_count_coef" => "0.0000881436609385893125467390135",
                    "total" => "1.20116",
                ],
                [
                    "type_label" => "Illegal service",
                    "risk_score_grade" => "high",
                    "type" => "ILLEGAL_SERVICE",
                    "total_count" => "0.004775495219010515294827824316",
                    "total_count_coef" => "0.00004775495219010515294827824316",
                    "total" => "0.650771",
                ], [
                    "type_label" => "Bridges",
                    "risk_score_grade" => "moderate",
                    "type" => "BRIDGES",
                    "total_count" => "0.002182523518323478533112871779",
                    "total_count_coef" => "0.00002182523518323478533112871779",
                    "total" => "0.297419",
                ], [
                    "type_label" => "Gambling",
                    "risk_score_grade" => "high",
                    "type" => "GAMBLING",
                    "total_count" => "0.001227440618586617814592177110",
                    "total_count_coef" => "0.0000122744061858661781459217711",
                    "total" => "0.167267",
                ], [
                    "type_label" => "P2P Exchange licensed",
                    "risk_score_grade" => "low",
                    "type" => "P2P_EXCHANGE_LICENSED",
                    "total_count" => "0.001150073855018438128107423254",
                    "total_count_coef" => "0.00001150073855018438128107423254",
                    "total" => "0.156724",
                ], [
                    "type_label" => "Scam",
                    "risk_score_grade" => "high",
                    "type" => "SCAM",
                    "total_count" => "0.0008121198638044622880838195265",
                    "total_count_coef" => "0.000008121198638044622880838195265",
                    "total" => "0.110670",
                ], [
                    "type_label" => "Payment system",
                    "risk_score_grade" => "moderate",
                    "type" => "PAYMENT_SYSTEM",
                    "total_count" => "0.0003258165894363253419257394685",
                    "total_count_coef" => "0.000003258165894363253419257394685",
                    "total" => "0.044400",
                ], [
                    "type_label" => "Online wallet",
                    "risk_score_grade" => "low",
                    "type" => "ONLINE_WALLET",
                    "total_count" => "0.00009445011987916539360194127700",
                    "total_count_coef" => "9.4450119879165393601941277E-7",
                    "total" => "0.012871",
                ], [
                    "type_label" => "ATM",
                    "risk_score_grade" => "moderate",
                    "type" => "ATM",
                    "total_count" => "0.00007155490008093712633148390894",
                    "total_count_coef" => "7.155490008093712633148390894E-7",
                    "total" => "0.009751",
                ], [
                    "type_label" => "Stolen assets",
                    "risk_score_grade" => "high",
                    "type" => "STOLEN_ASSETS",
                    "total_count" => "0.00003034350444412624524466064644",
                    "total_count_coef" => "3.034350444412624524466064644E-7",
                    "total" => "0.004135"
                ],
                [
                    "type_label" => "P2P Exchange unlicensed",
                    "risk_score_grade" => "moderate",
                    "type" => "P2P_EXCHANGE_UNLICENSED",
                    "total_count" => "5.870569179032889043707017450E-8",
                    "total_count_coef" => "5.87056917903288904370701745E-10",
                    "total" => "0.000008"
                ]
            ],
            "direct_entities" => [],
            "indirect_entities" => [],
            "discrete" => [
                "received" => [],
                "sent" => [],
            ],
            "link" => "https://explorer.coinkyt.com/explorer/address?id=trx-TEXAMPLEADDRESS0000000000000000003&blockchain=trx&token=",
            "contract_name" => "",
            "contract_symbol" => "",
            "is_contract_address" => false,
            "alerts" => null,
            "incoming_amount" => "6846.003554",
            "outgoing_amount" => "6781.29519",
            "balance" => "64.708364",
        ];
    }
}
