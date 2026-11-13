<?php

namespace App\Services\Aml\CoinKyt\Entity;

use App\Helpers\Development;
use App\Services\Aml\CoinKyt\Aml;

/**
 * CoinKYT account balance check.
 * GET /openapi/v1/entity/types
 * Returns {"id": ..., "current balance": ..., "balance": ..., "balance_used": ..., "expired_at": ...}
 */
class Types
{
    public function get(): array
    {
        if (Development::isLocal() || Development::isTesting()) {
            return $this->getLocalData();
        }
        return (new Aml('/entity/types'))->execute();
    }


    public function getLocalData(): array
    {
        return [
            "types" => [
                [
                    "id" => 25,
                    "type_label" => "Rewards/Fees",
                    "type" => "REWARDS_FEES",
                ],
                [
                    "id" => 22,
                    "type_label" => "Unknown owner",
                    "type" => "UNKNOWN_OWNER",
                ],
                [
                    "id" => 1,
                    "type_label" => "ATM",
                    "type" => "ATM",
                ],
                [
                    "id" => 2,
                    "type_label" => "Darknet marketplace",
                    "type" => "DARKNET_MARKETPLACE",
                ],
                [
                    "id" => 3,
                    "type_label" => "Darknet service",
                    "type" => "DARKNET_SERVICE",
                ],
                [
                    "id" => 4,
                    "type_label" => "Illegal service",
                    "type" => "ILLEGAL_SERVICE",
                ],
                [
                    "id" => 5,
                    "type_label" => "Scam",
                    "type" => "SCAM",
                ],
                [
                    "id" => 6,
                    "type_label" => "Scam crypto exchange",
                    "type" => "SCAM_CRYPTO_EXCHANGE",
                ],
                [
                    "id" => 7,
                    "type_label" => "Gambling",
                    "type" => "GAMBLING",
                ],
                [
                    "id" => 8,
                    "type_label" => "Stolen assets",
                    "type" => "STOLEN_ASSETS",
                ],
                [
                    "id" => 9,
                    "type_label" => "Mixing service",
                    "type" => "MIXING_SERVICE",
                ],
                [
                    "id" => 10,
                    "type_label" => "Ransom",
                    "type" => "RANSOM",
                ],
                [
                    "id" => 11,
                    "type_label" => "Licensed exchange service",
                    "type" => "EXCHANGE_LICENSED",
                ],
                [
                    "id" => 12,
                    "type_label" => "Unlicensed exchange service",
                    "type" => "EXCHANGE_UNLICENSED",
                ],
                [
                    "id" => 14,
                    "type_label" => "P2P Exchange unlicensed",
                    "type" => "P2P_EXCHANGE_UNLICENSED",
                ],
                [
                    "id" => 15,
                    "type_label" => "Miner",
                    "type" => "MINER",
                ],
                [
                    "id" => 16,
                    "type_label" => "Online marketplace",
                    "type" => "ONLINE_MARKETPLACE",
                ],
                [
                    "id" => 17,
                    "type_label" => "Online wallet",
                    "type" => "ONLINE_WALLET",
                ],
                [
                    "id" => 19,
                    "type_label" => "P2P Exchange licensed",
                    "type" => "P2P_EXCHANGE_LICENSED",
                ],
                [
                    "id" => 20,
                    "type_label" => "Payment system",
                    "type" => "PAYMENT_SYSTEM",
                ],
                [
                    "id" => 21,
                    "type_label" => "Other",
                    "type" => "OTHER",
                ],
                [
                    "id" => 24,
                    "type_label" => "Sanctions",
                    "type" => "SANCTIONS",
                ],
                [
                    "id" => 26,
                    "type_label" => "Terrorism financing",
                    "type" => "TERRORISM_FINANCING",
                ],
                [
                    "id" => 27,
                    "type_label" => "Decentralized exchange",
                    "type" => "DECENTRALIZED_EXCHANGE",
                ],
                [
                    "id" => 28,
                    "type_label" => "Malware",
                    "type" => "MALWARE",
                ],
                [
                    "id" => 29,
                    "type_label" => "Illegal arms trafficking",
                    "type" => "ILLEGAL_ARMS_TRAFFICKING",
                ],
                [
                    "id" => 30,
                    "type_label" => "Drugs trafficking",
                    "type" => "DRUGS_TRAFFICKING",
                ],
                [
                    "id" => 31,
                    "type_label" => "Child abuse material",
                    "type" => "CHILD_ABUSE_MATERIAL",
                ],
                [
                    "id" => 32,
                    "type_label" => "Privacy protocol",
                    "type" => "PRIVACY_PROTOCOL",
                ],
                [
                    "id" => 33,
                    "type_label" => "Sanctioned jurisdiction",
                    "type" => "SANCTIONED_JURISDICTION",
                ],
                [
                    "id" => 34,
                    "type_label" => "Seized assets",
                    "type" => "SEIZED_ASSETS",
                ],
                [
                    "id" => 35,
                    "type_label" => "Extremism",
                    "type" => "EXTREMISM",
                ],
                [
                    "id" => 36,
                    "type_label" => "Bridges",
                    "type" => "BRIDGES",
                ],
                [
                    "id" => 37,
                    "type_label" => "Lending protocol",
                    "type" => "LENDING_PROTOCOL",
                ],
                [
                    "id" => 38,
                    "type_label" => "Seizure of funds",
                    "type" => "SEIZURE_OF_FUNDS",
                ]
            ]
        ];
    }
}
