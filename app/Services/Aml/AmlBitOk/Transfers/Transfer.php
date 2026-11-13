<?php

namespace App\Services\Aml\AmlBitOk\Transfers;

use App\Services\Aml\AmlBitOk\Aml;
use App\Helpers\Development;

class Transfer
{
    public function getTransfer(string $id): array
    {
        if (Development::isLocal() || Development::isTesting()) {
            return $this->getLocalData();
        }

        $runner = new Aml(sprintf('/v1/transfers/%s/', $id));
        return $runner->execute();
    }

    private function getLocalData(): array
    {
        return [
            "id" => "00000000-0000-0000-0000-000000000011",
            "client_id" => null,
            "attempt_id" => null,
            "registered_at" => "2026-04-01T17:20:27.469409+03:00",
            "risk_level" => "none",
            "risk_score" => 0.0,
            "network" => "TRX",
            "token_id" => "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t",
            "token_symbol" => "USDT",
            "tx_status" => "bound",
            "tx_hash" => "0000000000000000000000000000000000000000000000000000000000000002",
            "occurred_at" => "2026-03-03T12:47:03Z",
            "input_address" => "TEXAMPLEADDRESS0000000000000000005",
            "output_address" => "TEXAMPLEADDRESS0000000000000000006",
            "direction" => "incoming",
            "amount" => 5840.0,
            "value_in_fiat" => 5840.21,
            "check_state" => [
                "exposure" => "checked",
                "exposure_checked_at" => "2026-04-01T14:20:28.025013Z",
                "counterparty" => "none",
                "counterparty_checked_at" => null,
                "sanctions" => "none",
                "sanctions_checked_at" => null,
            ],
            "fiat_currency" => "USD",
        ];
    }

}
