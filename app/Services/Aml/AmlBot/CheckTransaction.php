<?php

namespace App\Services\Aml\AmlBot;

use App\Helpers\Development;

class CheckTransaction
{

    /**
     * https://docs.amlbot.com/webApi/requests#aml-transaction-verification
     * @param string $asset
     * @param string $address
     * @param string $hash
     * @param string $direction direction - deposit if funds were sent to your address,
     *  withdrawal if you sent funds from your address
     * @return array
     * @return array|\GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|mixed
     * @throws \JsonException
     */
    public function check(string $asset, string $address, string $hash,
                          string $direction = 'deposit',
                        string $flow = 'fast'
    ): array
    {
        if (Development::isLocal()) {
            return $this->getLocalData();
        }
//asset - currency that will be checked (BTC, BCH, LTC, XRP, ETC, ADA, ETH, TRX, ALGO, XLM, MATIC)

        $accessKey = config('services.aml.access_key');
        $accessId = config('services.aml.access_id');

        $direction = $direction ?: 'deposit';
        $flow = $flow ?: 'fast';

        $runner = new Aml('/', [
            'direction' => $direction,
            'hash' => $hash,
            'asset' => strtoupper($asset),
            'address' => $address,
            'token' => md5(sprintf('%s:%s:%s', $hash, $accessKey, $accessId)), // 'H:A:T' where: H - hash A - Access Key T - Access Id
            'locale' => 'en_US',
            'flow' => $flow,
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
            'discount' => 0,
            'discountDueTime' => 0,
            'promoFlow' => false,
            'data' => [
                'riskscore' => null, //0.288
                'signals' => [
                    'atm' => 0,
                    'child_exploitation' => 0,
                    'dark_market' => 0,
                    'dark_service' => 0,
                    'enforcement_action' => 0,
                    'exchange_fraudulent' => 0,
                    'gambling' => 0.001,
                    'illegal_service' => 0,
                    'liquidity_pools' => 0,
                    'marketplace' => 0,
                    'miner' => 0,
                    'mixer' => 0,
                    'other' => 0.007,
                    'payment' => 0.003,
                    'ransom' => 0,
                    'sanctions' => 0.007,
                    'scam' => 0,
                    'seized_assets' => 0,
                    'stolen_coins' => 0,
                    'terrorism_financing' => 0,
                    'wallet' => 0.001,
                    'exchange' => 0.874,
                    'risky_exchange' => 0.106,
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
                'address' => 'TEXAMPLEADDRESS0000000000000000010',
                'created_at' => 1707120672,
                'amount' => 39794904,
                'risky_volume' => 320280.91750503,
                'direction' => 'deposit',
                'tx' => '0000000000000000000000000000000000000000000000000000000000000005',
                'risky_volume_fiat' => 32,
                'fiat' => 3976,
                'fiat_code_effective' => 'usd',
                'counterparty' => [
                    'address' => 'TEXAMPLEADDRESS0000000000000000011',
                ],
                'tokenDetails' => [
                    'issuer' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
                    'code' => 'USDT',
                    'token_id' => 9,
                    'precision' => 6,
                ],
                'blackListsConnections' => false,
                'hasBlackListFlag' => false,
                'pdfReport' => '#',
                'memo' => '',
                'uid' => 'fake_65c0981fdcf9a458383897',
                'asset' => 'TRX',
                'network' => 'TRX',
                'status' => 'success',
                'timestamp' => '2024-02-05 08:11:11',
                'flow' => 'fast',
                '_type' => 7,
            ]
        ];
    }
}
