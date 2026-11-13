<?php

namespace App\Services\Aml\GetBlock;


use App\Models\Wallet\Transaction;

class GetResult
{

    public function getResultByTransaction(Transaction $transaction, string $hash): array
    {
        return $this->getResult($transaction->id, $hash);
    }

    public function getResult($id, string $hash): array
    {
        $runner = new GetBlock([
            'id' => $id,
            'method' => 'checkup.getresult',
            'params' => [
                'hash' => $hash,
            ],
        ]);

        return $runner->post();
    }

    public function getTest(): array
    {
        return [
            "jsonrpc" => "2.0",
            "result" => [
                "check" => [
                    "hash" => "00000000-0000-0000-0000-000000000002",
                    "provider" => "BitOK",
                    "currency" => "TRX",
                    "address" => "TEXAMPLEADDRESS0000000000000000002",
                    "transaction" => "0000000000000000000000000000000000000000000000000000000000000001",
                    "status" => "SUCCESS",
                    "initDate" => "2026-02-05T16:13:28+00:00",
                    "resultDate" => "2026-02-05T16:13:30+00:00",
                    "pdfLink" => "https://getblock.net/en/report-download/00000000-0000-0000-0000-000000000002",
                    "shareLink" => "https://getblock.net/en/TRX/tx/0000000000000000000000000000000000000000000000000000000000000001?amlcheckup=00000000-0000-0000-0000-000000000002",
                    "counterparty" => [
                        "id" => "unknown",
                        "name" => "unknown",
                        "slug" => "unknown",
                        "type" => "unknown",
                    ],
                    "report" => [
                        "amount" => 38885,
                        "fiat" => 38835.1,
                        "fiat_code_effective" => "USD",
                        "riskscore" => 0,
                        "risky_volume" => null,
                        "risky_volume_fiat" => null,
                        "signals" => [
                            "atm" => 0,
                            "child_exploitation" => 0,
                            "dark_market" => 0,
                            "dark_service" => 0,
                            "enforcement_action" => 0,
                            "exchange_fraudulent" => 0,
                            "exchange_licensed" => 0,
                            "exchange_unlicensed" => 0,
                            "gambling" => 0,
                            "illegal_service" => 0,
                            "liquidity_pools" => 0,
                            "marketplace" => 0,
                            "miner" => 0,
                            "mixer" => 0,
                            "other" => 1,
                            "p2p_exchange_licensed" => 0,
                            "p2p_exchange_unlicensed" => 0,
                            "payment" => 0,
                            "ransom" => 0,
                            "sanctions" => 0,
                            "scam" => 0,
                            "seized_assets" => 0,
                            "stolen_coins" => 0,
                            "terrorism_financing" => 0,
                            "wallet" => 0,
                        ]
                    ]
                ],
                "meta" => [
                    "cheks_left" => 350,
                    "cheks_used" => "1"
                ]
            ],
            "id" => 2
        ];
    }
}
