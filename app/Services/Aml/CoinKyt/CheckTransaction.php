<?php

namespace App\Services\Aml\CoinKyt;

use App\Helpers\Development;

/**
 * AML transaction check by TxID.
 * GET /openapi/v1/transaction?blockchain={blockchain}&transaction={tx_hash}
 */
class CheckTransaction
{
    public function check(string $txHash, string $blockchain): array
    {
        if (Development::isLocal() || Development::isTesting()) {
            return $this->getLocalData();
        }

        return (new Aml('/transaction', [
            'blockchain' => $blockchain,
            'transaction' => $txHash,
        ]))->execute();
    }


    private  function getLocalData(): array
    {
        return [
            "id" => "00000000-0000-0000-0000-000000000003",
            "blockchain" => "trx",
            "token" => "",
            "address" => "0000000000000000000000000000000000000000000000000000000000000002",
            "risk_score" => "0.1000000000249021",
            "risk_score_grade" => "low",
            "inputs" => 1,
            "outputs" => 1,
            "from_entity" => [],
            "to_entity" => [],
            "indirects" => [
                [
                    "type_label" => "Decentralized exchange",
                    "risk_score_grade" => "moderate",
                    "type" => "DECENTRALIZED_EXCHANGE",
                    "total_count" => "47.05882352941176470588235294",
                    "total_count_coef" => "0.4705882352941176470588235294",
                    "total" => "0.000008",
                ],
                [
                    "type_label" => "Unlicensed exchange service",
                    "risk_score_grade" => "moderate",
                    "type" => "EXCHANGE_UNLICENSED",
                    "total_count" => "35.29411764705882352941176471",
                    "total_count_coef" => "0.3529411764705882352941176471",
                    "total" => "0.000006",
                ],
                [
                    "type_label" => "Licensed exchange service",
                    "risk_score_grade" => "low",
                    "type" => "EXCHANGE_LICENSED",
                    "total_count" => "17.64705882352941176470588235",
                    "total_count_coef" => "0.1764705882352941176470588235",
                    "total" => "0.000003",
                ]
            ],
            "block_number" => 80625232,
            "timestamp" => "2026-03-03T12:47:03Z",
            "link" => "https://explorer.coinkyt.com/explorer/transaction?id=trx-0000000000000000000000000000000000000000000000000000000000000002&blockchain=trx",
            "too_many_indirects" => false,
            "alerts" => null,
        ];
    }

}
