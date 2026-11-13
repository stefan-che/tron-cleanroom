<?php

namespace App\Services\Aml\AmlBot;

use App\Helpers\Development;

class CheckAddress
{

    /**
     * https://docs.amlbot.com/webApi/requests#aml-address-verification
     * @param string $asset
     * @param string $address
     * @param string $flow [advanced, fast]
     * @return array
     * @return array|\GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|mixed
     * @throws \JsonException
     */
    public function check(string $asset, string $address, string $flow = 'fast'): array
    {
        if (Development::isLocal()) {
        }
//asset - currency that will be checked (BTC, BCH, LTC, XRP, ETC, ADA, ETH, TRX, ALGO, XLM, MATIC)

        $accessKey = config('services.aml.access_key');
        $accessId = config('services.aml.access_id');

        $runner = new Aml('/', [
            'hash' => $address,
            'asset' => strtoupper($asset),
            'token' => md5(sprintf('%s:%s:%s', $address, $accessKey, $accessId)), // 'H:A:T' where: H - hash A - Access Key T - Access Id
            'locale' => 'en_US',
            'flow' => $flow,
            'async' => true,
        ]);

        return $runner->post();
    }

    private function getLocalData(): array
    {
        return [
            'result' => true,
            'balance' => 10,
            'amlFlow' => 'fast',
            'devModeUsed' => true,
            'termsConfirmRequired' => false,
            'proMode' => '0',
            'discount' => 0,
            'discountDueTime' => 0,
            'promoFlow' => false,
            'data' => [
                'riskscore' => 0.1,
                'signals' => [
                    'atm' => 0,
                    'child_exploitation' => 0,
                    'dark_market' => 0,
                    'dark_service' => 0,
                    'enforcement_action' => 0,
                    'exchange_fraudulent' => 0,
                    'gambling' => 0,
                    'illegal_service' => 0,
                    'liquidity_pools' => 0,
                    'marketplace' => 0,
                    'miner' => 0,
                    'mixer' => 0,
                    'other' => 0,
                    'payment' => 0,
                    'ransom' => 0,
                    'sanctions' => 0,
                    'scam' => 0,
                    'seized_assets' => 0,
                    'stolen_coins' => 0,
                    'terrorism_financing' => 0,
                    'wallet' => 0,
                    'exchange' => 1,
                    'risky_exchange' => 0,
                    'p2p_exchange' => 0,
                    'p2p_exchange_mlrisk_high' => 0,
                    'exchange_mlrisk_high' => 0,
                    'exchange_mlrisk_low' => 0,
                    'exchange_mlrisk_moderate' => 0,
                    'exchange_mlrisk_veryhigh' => 0,
                    'p2p_exchange_mlrisk_low' => 0,
                    'fraud_shop' => 0,
                    'illicit_actor_org' => 0,
                    'high_risk_jurisdiction' => 0,
                    'infrastructure_as_a_service' => 0,
                    'lending_contract' => 0,
                    'decentralized_exchange_contract' => 0,
                    'smart_contract' => 0,
                    'token_smart_contract' => 0,
                    'protocol_privacy' => 0,
                    'merchant_services' => 0,
                    'ico' => 0,
                    'unnamed_service' => 0,
                    'special_measures' => 0,
                    'online_pharmacy' => 0,
                    'bridge' => 0,
                    'malware' => 0,
                    'nft_platform_collection' => 0,
                ],
                'updated_at' => 1700000000,
                'address' => 'TEXAMPLEADDRESS0000000000000000004',
                'fiat_code_effective' => 'usd',
                'counterparty' => [
                    'address' => 'TEXAMPLEADDRESS0000000000000000004',
                ],
                'reportedAddressBalance' => null,
                'addressDetailsData' => [
                    'created' => '1970-01-01 00:00:00',
                    'n_txs' => 0,
                    'final_n_txs' => 0,
                    'reported_balance_precision' => 6,
                    'balance' => '0',
                    'balance_approximate' => 0,
                    'tokens' => [
                        'trc10' => [],
                        'trc20' => [
                            [
                                'token_address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
                                'token_decimals' => 6,
                                'token_name' => 'Tether USD',
                                'token_symbol' => 'USDT',
                                'token_logo' => 'https://static.tronscan.org/production/logo/usdtlogo.png',
                                'balance' => '399000000',
                                'balance_approximate' => 399,
                                'balance_usd' => 398.5548772196,
                            ]
                        ]
                    ]
                ],
                'blackListsConnections' => false,
                'hasBlackListFlag' => false,
                'pdfReport' => '#',
                'memo' => '',
                'uid' => '66560b65b03af030002185',
                'asset' => 'TRX',
                'network' => 'TRX',
                'status' => 'success',
                'timestamp' => '2024-05-28 16:50:45',
                'flow' => 'fast',
                '_type' => 9,
                'cost' => 1,

            ]
        ];
    }
}
