<?php

namespace App\Services\Aml\AmlBitOk\Basics;

use App\Services\Aml\AmlBitOk\Aml;

class Networks
{

    public function getNetworks(): array
    {
        $runner = new Aml('/v1/basics/networks/');
        return $runner->execute();
    }

    private function getLocalData(): array
    {
        return [
            'page' => 1,
            'page_size' => 20,
            'total_pages' => 1,
            'total_items' => 4,
            'items' => [
                [
                    'code' => 'ETH',
                    'name' => 'Ethereum',
                    'check_levels' => [
                        'transfer_exposure' => [
                            'base' => 'none',
                            'extra' => [
                                0 => 'full'
                            ]
                        ],
                        'transfer_counterparty' => [
                            'base' => 'limited',
                            'extra' => [
                                'full'
                            ]
                        ]
                    ]
                ],
                [
                    'code' => 'TRX',
                    'name' => 'Tron',
                    'check_levels' => [
                        'transfer_exposure' => [
                            'base' => 'none',
                            'extra' => [
                                'full'
                            ]
                        ],
                        'transfer_counterparty' => [
                            'base' => 'limited',
                            'extra' => [
                                'full'
                            ]
                        ]
                    ]
                ],
                [
                    'code' => 'BTC',
                    'name' => 'Bitcoin',
                    'check_levels' => [
                        'transfer_exposure' => [
                            'base' => 'full',
                            'extra' => []
                        ],
                        'transfer_counterparty' => [
                            'base' => 'full',
                            'extra' => []
                        ]
                    ]
                ],
                [
                    'code' => 'BSC',
                    'name' => 'BNB Chain',
                    'check_levels' => [
                        'transfer_exposure' => [
                            'base' => 'none',
                            'extra' => [
                                'full'
                            ],
                        ],
                        'transfer_counterparty' => [
                            'base' => 'limited',
                            'extra' => [
                                'full'
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

}
