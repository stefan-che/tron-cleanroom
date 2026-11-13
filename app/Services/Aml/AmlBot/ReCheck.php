<?php

namespace App\Services\Aml\AmlBot;

use App\Helpers\Development;

class ReCheck
{
    /**
     * https://docs.amlbot.com/webApi/requests#recheck
     * @param string $amlUid
     * @return array
     * @return array|\GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|mixed
     * @throws \JsonException
     */
    public function reCheck(string $amlUid): array
    {
        if (Development::isLocal()) {
            return $this->getLocalData();
        }

        $accessKey = config('services.aml.access_key');
        $accessId = config('services.aml.access_id');

        $runner = new Aml('/recheck', [
            'accessId' => $accessId,
            'uid' => $amlUid,
            'token' => md5(sprintf('%s:%s:%s', $amlUid, $accessKey, $accessId)), // md5("uid:access_key:accessId")
        ]);

        return $runner->post();
    }

    private function getLocalData(): array
    {
        return [
            'result' => true,
            'balance' => 1000,
            'amlFlow' => 'fast',
            'termsConfirmRequired' => false,
            'proMode' => '0',
            'discount' => 0,
            'discountDueTime' => 0,
            'promoFlow' => false,
            'data' => [
                'riskscore' => 0.2,
                'signals' => [
                    'exchange' => 0.865,
                    'risky_exchange' => 0.127,
                    'p2p_exchange' => 0,
                    'enforcement_action' => 0,
                    'atm' => 0,
                    'child_exploitation' => 0,
                    'dark_market' => 0,
                    'dark_service' => 0,
                    'exchange_fraudulent' => 0,
                    'gambling' => 0,
                    'illegal_service' => 0,
                    'liquidity_pools' => 0,
                    'marketplace' => 0,
                    'miner' => 0,
                    'mixer' => 0,
                    'other' => 0,
                    'p2p_exchange_mlrisk_high' => 0,
                    'payment' => 0,
                    'ransom' => 0,
                    'sanctions' => 0.004,
                    'scam' => 0,
                    'seized_assets' => 0,
                    'stolen_coins' => 0,
                    'terrorism_financing' => 0,
                    'wallet' => 0.003,
                    'infrastructure_as_a_service' => 0,
                    'decentralized_exchange_contract' => 0,
                    'merchant_services' => 0,
                    'unnamed_service' => 0,
                    'malware' => 0,
                ],
                'updated_at' => 1700000000,
                'address' => 'TEXAMPLEADDRESS0000000000000000008',
                'created_at' => 1700000000,
                'amount' => 1000000000,
                'risky_volume' => 1000000,
                'direction' => 'deposit',
                'tx' => '0000000000000000000000000000000000000000000000000000000000000004',
                'risky_volume_fiat' => 100,
                'fiat' => 100000,
                'fiat_code_effective' => 'usd',
                'counterparty' => [
                    'address' => 'TEXAMPLEADDRESS0000000000000000009',
                    'received_fiat_amount' => 1000000,
                    'sent_fiat_amount' => 1000000,
                    'signals' => [
                        'in' => [
                            'exchange' => 0.865,
                            'risky_exchange' => 0.127,
                            'p2p_exchange' => 0,
                            'enforcement_action' => 0,
                            'atm' => 0,
                            'child_exploitation' => 0,
                            'dark_market' => 0,
                            'dark_service' => 0,
                            'exchange_fraudulent' => 0,
                            'gambling' => 0,
                            'illegal_service' => 0,
                            'liquidity_pools' => 0,
                            'marketplace' => 0,
                            'miner' => 0,
                            'mixer' => 0,
                            'other' => 0,
                            'p2p_exchange_mlrisk_high' => 0,
                            'payment' => 0,
                            'ransom' => 0,
                            'sanctions' => 0.004,
                            'scam' => 0,
                            'seized_assets' => 0,
                            'stolen_coins' => 0,
                            'terrorism_financing' => 0,
                            'wallet' => 0.003,
                            'infrastructure_as_a_service' => 0,
                            'decentralized_exchange_contract' => 0,
                            'merchant_services' => 0,
                            'unnamed_service' => 0,
                            'malware' => 0,
                        ],
                        'out' => [
                            'exchange' => 0.992,
                            'risky_exchange' => 0.007,
                            'p2p_exchange' => 0,
                            'enforcement_action' => 0,
                            'atm' => 0,
                            'child_exploitation' => 0,
                            'dark_market' => 0,
                            'dark_service' => 0,
                            'exchange_fraudulent' => 0,
                            'gambling' => 0,
                            'illegal_service' => 0,
                            'liquidity_pools' => 0,
                            'marketplace' => 0,
                            'miner' => 0,
                            'mixer' => 0,
                            'other' => 0,
                            'p2p_exchange_mlrisk_high' => 0,
                            'payment' => 0,
                            'ransom' => 0,
                            'sanctions' => 0,
                            'scam' => 0,
                            'seized_assets' => 0,
                            'stolen_coins' => 0,
                            'terrorism_financing' => 0,
                            'wallet' => 0,
                            'infrastructure_as_a_service' => 0,
                            'decentralized_exchange_contract' => 0,
                            'merchant_services' => 0,
                            'unnamed_service' => 0,
                            'malware' => 0,
                        ]
                    ]
                ],
                'tokenDetails' => [
                    'issuer' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
                    'code' => 'USDT',
                    'token_id' => 9,
                    'precision' => 6,
                ],
                'blackListsConnections' => false,
                'hasBlackListFlag' => false,
                'pdfReport' => 'https://example.com/aml-report/<report_id>.pdf',
                'memo' => '',
                'extras' => [
                    'tags' => [],
                    'services' => []
                ],
                'customerIsB2B' => true,
                'confirmed_at' => 1700000000,
                'uid' => '<check_uid>',
                'asset' => 'TRX',
                'network' => 'TRX',
                'status' => 'success',
                'timestamp' => '2024-01-01 00:00:00',
                'flow' => 'advanced',
                '_type' => 7,
                'cost' => 1,
            ]
        ];

    }
}
