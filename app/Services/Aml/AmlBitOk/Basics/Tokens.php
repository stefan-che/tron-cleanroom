<?php

namespace App\Services\Aml\AmlBitOk\Basics;

use App\Services\Aml\AmlBitOk\Aml;

class Tokens
{

    public function getTokens(): array
    {
        $runner = new Aml('/v1/basics/tokens/');
        return $runner->execute();
    }

    private function getLocalData(): array
    {
        return [
            "page" => 1,
            "page_size" => 20,
            "total_pages" => 208,
            "total_items" => 4142,
            "items" => [
                [
                    "network" => "BTC",
                    "token_id" => "native",
                    "type" => "native",
                    "symbol" => "BTC",
                    "name" => "Bitcoin",
                    "check_levels" => [
                        "transfer_exposure" => "full",
                        "transfer_counterparty" => "full"
                    ],
                ],
                [
                    "network" => "ETH",
                    "token_id" => "native",
                    "type" => "native",
                    "symbol" => "ETH",
                    "name" => "Ethereum",
                    "check_levels" => [
                        "transfer_exposure" => "full",
                        "transfer_counterparty" => "full"
                    ]
                ],
                [
                    "network" => "BSC",
                    "token_id" => "0x55d398326f99059ff775485246999027b3197955",
                    "type" => "erc20",
                    "symbol" => "USDT",
                    "name" => "Tether USD",
                    "check_levels" => [
                        "transfer_exposure" => "full",
                        "transfer_counterparty" => "full"
                    ]
                ],
                [
                    "network" => "TRX",
                    "token_id" => "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t",
                    "type" => "erc20",
                    "symbol" => "USDT",
                    "name" => "Tether USD",
                    "check_levels" => [
                        "transfer_exposure" => "full",
                        "transfer_counterparty" => "full"
                    ]
                ],
                [
                    "network" => "ETH",
                    "token_id" => "0xdac17f958d2ee523a2206206994597c13d831ec7",
                    "type" => "erc20",
                    "symbol" => "USDT",
                    "name" => "Tether",
                    "check_levels" => [
                        "transfer_exposure" => "full",
                        "transfer_counterparty" => "full"
                    ]
                ]]];
    }

}
