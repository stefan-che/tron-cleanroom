<?php

namespace App\Services\Aml\WellWallet;

use App\Helpers\Development;

/**
 * POST /v1/aml-report-recheck
 * @docs https://docs.wellwallet.io/api-reference/aml/get-aml-report-by-uid
 * Fetches the result of an asynchronous check by uid
 */
class ReCheck
{
    /**
     * @param string $uid uid returned when the asynchronous check was started
     * @return array
     * @throws \JsonException
     */
    public function reCheck(string $uid): array
    {
        if (Development::isLocal()) {
            return $this->getLocalData();
        }

        $runner = new WellWallet('/v1/aml-report-recheck', ['uid' => $uid]);

        return $runner->post();
    }

    /**
     * @return array
     */
    private function getLocalData(): array
    {
        return [
            'code' => '200',
            'data' => [
                'params' => [
                    'risk' => [
                        'score' => 15
                    ],
                    'owner' => 'N/A',
                    'balances' => [
                        'TRX' => 0,
                        'USDT' => 0
                    ],
                    'transactions' => [
                        'total' => 0,
                        'inputs' => 0,
                        'outputs' => 0
                    ],
                    'types_of_funding' => [
                        'Exchange' => 100
                    ],
                    'sources_of_funding' => [
                        'Bybit' => 100
                    ]
                ],
                'message' => '<b>Transaction ID: </b>0000000000000000000000000000000000000000000000000000000000000006\n-------------------\n\n🟢 - Low; 🟡 - Medium;\n🟠 - High; 🔴 - Very high\n\n<b>Risk: </b>🟢 (15)\n\n🔍 <b>Sources of funding:</b>\n\n<b>By type:</b>\n\nExchange - 100%\n',
                'user_id' => '100000001',
                'pdf_link' => 'https://example.com/report.pdf',
                'aml_score' => 15,
                'checks_balance' => '14'
            ]
        ];
    }
}
