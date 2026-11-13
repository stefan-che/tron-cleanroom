<?php

namespace App\Services\Aml\AmlBitOk\Basics;

use App\Services\Aml\AmlBitOk\Aml;

class EntryCategories
{

    public function getCategories(): array
    {
        $runner = new Aml('/v1/basics/entity-categories/');
        return $runner->execute();
    }

    private function getLocalData(): array
    {
        return [
            "page" => 1,
            "page_size" => 20,
            "total_pages" => 2,
            "total_items" => 40,
            "items" => [
                [
                    "code" => "illegal_service",
                    "name" => "Illegal Service",
                ],
                [
                    "code" => "online_pharmacy",
                    "name" => "Online pharmacy",
                ],
                [
                    "code" => "scam",
                    "name" => "Scam",
                ],
                [
                    "code" => "stolen_funds",
                    "name" => "Stolen Funds",
                ],
                [
                    "code" => "terrorist_financing",
                    "name" => "Terrorist Financing",
                ],
                [
                    "code" => "exchange",
                    "name" => "Exchange",
                ],
                [
                    "code" => "p2p_exchange",
                    "name" => "P2P exchange",
                ],
                [
                    "code" => "high_risk_exchange",
                    "name" => "High-Risk Exchange",
                ],
                [
                    "code" => "mining",
                    "name" => "Mining",
                ],
                [
                    "code" => "mining_pool",
                    "name" => "Mining Pool",
                ],
                [
                    "code" => "lending",
                    "name" => "Lending",
                ],
                [
                    "code" => "bridge",
                    "name" => "Bridge",
                ],
                [
                    "code" => "ico",
                    "name" => "ICO",
                ]
            ]
        ];
    }

}
