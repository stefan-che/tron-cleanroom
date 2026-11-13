<?php

namespace App\Services\Aml\CoinKyt;

use App\Helpers\Development;

/**
 * CoinKYT account balance check.
 * GET /openapi/v1/balance
 * Returns {"id": ..., "current balance": ..., "balance": ..., "balance_used": ..., "expired_at": ...}
 */
class GetBalance
{
    public function get(): array
    {
        if (Development::isLocal() || Development::isTesting()) {
            return $this->getLocalData();
        }
        return (new Aml('/balance'))->execute();
    }


    public function getLocalData(): array
    {
        return [
            "id" => 21172,
            "current_balance" => 13,
            "balance" => 15,
            "balance_used" => 2,
            "expired_at" => "2100-12-01",
        ];
    }
}
