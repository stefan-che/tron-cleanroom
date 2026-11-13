<?php

namespace App\Services\Aml\WellWallet;

use App\Helpers\Development;
use Illuminate\Support\Facades\Log;

/**
 * GET /user-balances
 */
class UserBalances
{

    public function balance(): array
    {
        if (Development::isLocal()) {
            return $this->getLocalData();
        }

        $runner = new WellWallet('/v1/user-balances');

        return $runner->execute();
    }

    /**
     * @return array
     */
    private function getLocalData(): array
    {
        return [
            'code' => '200',
            'data' => [
                'result' => 'success',
                'balances' => [
                    'accounting' => [
                        'trx_eth' => [
                            'USDT' => [
                                'blockchain' => 0,
                                'transit' => 0,
                                'available' => 0,
                            ]
                        ]
                    ],
                    'user' => [
                        'trx' => [
                            'USDT' => [
                                'available' => 0
                            ]
                        ]
                    ]
                ],
                'user_id' => '100000001'
            ]

        ];
    }

    public function getBalance(): float
    {
        $data = $this->balance();

        return $data['data']['balances']['accounting']['trx_eth']['USDT']['available'] ?? 0;
        return $data['data']['balances']['user']['trx']['USDT']['available'] ?? 0;
    }
}

