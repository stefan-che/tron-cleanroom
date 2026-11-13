<?php

namespace App\Services\Tron;

use IEXBase\TronAPI\Tron as TronIEXBase;

class Tron extends TronIEXBase
{
    public const string USDT_TRC20_CONTRACT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
    public const string USDT_TRC20_SHASTA_CONTRACT = 'TG3XXyExBkPp9nzdajDZsozEu4BkaSJozs';



    public function getTrxTransaction(string $address): array
    {

        $response = $this->manager->request("/v1/accounts/{$address}/transactions", [
            'limit' => 5,
        ], 'get');

        if (empty($response['data'])) {
            return [];
        }

        return array_filter(array_map(function ($tx) use ($address) {
            if (!isset($tx['raw_data']['contract'][0]) ||
                $tx['raw_data']['contract'][0]['type'] !== 'TransferContract') {
                return null;
            }

            $contractValue = $tx['raw_data']['contract'][0]['parameter']['value'] ?? [];

            $to = $this->fromHex($contractValue['to_address'] ?? '');

            return [
                'transaction_id' => $tx['txID'] ?? null,
                'block_timestamp' => $tx['block_timestamp'] ?? null,
                'from' => $this->fromHex($contractValue['owner_address'] ?? ''),
                'to' => $to,
                'direction' => $to === $address ? 'in' : 'out',
                'amount' => ($contractValue['amount'] ?? 0) / 1_000_000,
                'confirmed' => ($tx['ret'][0]['contractRet'] ?? '') === 'SUCCESS'
            ];
        }, $response['data']));
    }


    public function getUsdtTransaction(string $address, string $minTimestamp = '-1 week', int $limit = 200): array
    {

        $response = $this->manager->request("/v1/accounts/{$address}/transactions/trc20", [
            'only_confirmed' => 'true',
            'limit' => $limit,
            'min_timestamp' => strtotime($minTimestamp) * 1000,
        ], 'get');

        if (empty($response['data'])) {
            return [];
        }

        return array_filter(array_map(function ($tx) use($address){
            $to = $tx['to'] ?? null;

            return [
                'transaction_id' => $tx['transaction_id'] ?? null,
                'block_timestamp' => $tx['block_timestamp'] ?? null,
                'direction' => $to === $address ? 'in' : 'out',
                'from' => $tx['from'] ?? null,
                'to' => $to,
                'amount' => ($tx['value'] ?? 0) / 1_000_000, // 6 decimals
                'type' => $tx['type'] ?? null,
                'status' => $tx['status'] ?? null
            ];
        }, $response['data']));


        try {

        } catch (\Exception $e) {
            $this->addLog('error method:getUsdtTransactions', 'Failed to fetch USDT transactions', [
                'address' => $address,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }


    public function getTransactionConfirmations(string $txId): int
    {

        $transaction = $this->manager->request('/walletsolidity/gettransactioninfobyid', [
            'value' => $txId
        ]);

        if (empty($transaction['blockNumber'])) {
            return 0;
        }

        $currentBlock = $this->getCurrentBlock();

        if (empty($currentBlock['block_header']['raw_data']['number'])) {
            return 0;
        }

        $currentBlockNumber = $currentBlock['block_header']['raw_data']['number'];

        $blockConfirmations = max(0, $currentBlockNumber - $transaction['blockNumber']);

        return min(19, (int)($blockConfirmations / 3));

    }

    public static function isTestNetwork(): bool
    {
        return config('tron.trongrid.full_node', '') === 'https://api.shasta.trongrid.io';
    }

    public static function getContractAddress(): string
    {
        return self::isTestNetwork() ? self::USDT_TRC20_SHASTA_CONTRACT : self::USDT_TRC20_CONTRACT;
    }

    /**
     * Overrides freezeBalance to use the FreezeBalanceV2 API
     */
    public function freezeBalance(float $amount = 0, int $duration = 3, string $resource = 'BANDWIDTH', string $owner_address = null): array
    {
        if($owner_address == null) {
            $owner_address = $this->address['hex'];
        }

        if (!in_array($resource, ['BANDWIDTH', 'ENERGY'])) {
            throw new \Exception('Invalid resource provided: Expected "BANDWIDTH" or "ENERGY"');
        }

        if (!is_float($amount) || $amount <= 0) {
            throw new \Exception('Invalid amount provided');
        }

        if(!is_int($duration) || $duration < 3) {
            throw new \Exception('Invalid duration provided, minimum of 3 days');
        }

        $freeze = $this->getManager()->request('wallet/freezebalancev2', [
            'owner_address' => $owner_address,
            'frozen_balance' => $this->toTron($amount),
            'resource' => $resource
        ]);

        $signedTransaction = $this->signTransaction($freeze);
        $response = $this->sendRawTransaction($signedTransaction);

        return array_merge($response, $signedTransaction);
    }

    /**
     * Unfreezes TRX (FreezeBalanceV2 API)
     * IMPORTANT: unfreezing is only possible 3 days after freezing.
     * @param float $amount TRX amount to unfreeze (0 = unfreeze all)
     * @param string $resource Resource type: 'BANDWIDTH' or 'ENERGY'
     * @param string|null $owner_address Owner address (defaults to the current one)
     * @return array
     * @throws \Exception
     */
    public function unfreezeBalanceV2(float|string $amount = 0, string|null $resource = 'BANDWIDTH', string $owner_address = null): array
    {
        if($owner_address == null) {
            $owner_address = $this->address['hex'];
        }

        if (!in_array($resource, ['BANDWIDTH', 'ENERGY'])) {
            throw new \Exception('Invalid resource provided: Expected "BANDWIDTH" or "ENERGY"');
        }

        $params = [
            'owner_address' => $owner_address,
            'resource' => $resource
        ];

        // Add the amount if given (otherwise everything is unfrozen)
        if ($amount > 0) {
            $params['unfreeze_balance'] = $this->toTron($amount);
        }

        $unfreeze = $this->getManager()->request('wallet/unfreezebalancev2', $params);

        $signedTransaction = $this->signTransaction($unfreeze);
        $response = $this->sendRawTransaction($signedTransaction);

        return array_merge($response, $signedTransaction);
    }

    public function getAccountV1(string $address): array
    {
        /*array:3 [
        "data" => array:1 [
        0 => array:10 [
        "owner_permission" => array:3 [
        "keys" => array:1 [
        0 => array:2 [
        "address" => "TEXAMPLEADDRESS0000000000000000014"
            "weight" => 1
          ]
        ]
        "threshold" => 1
        "permission_name" => "owner"
      ]
      "account_resource" => array:2 [
        "energy_window_optimized" => true
        "energy_window_size" => 28800000
      ]
      "active_permission" => array:1 [
        0 => array:6 [
        "operations" => "7fff1fc0033ec30f000000000000000000000000000000000000000000000000"
          "keys" => array:1 [
        0 => array:2 [
        "address" => "TEXAMPLEADDRESS0000000000000000014"
              "weight" => 1
            ]
          ]
          "threshold" => 1
          "id" => 2
          "type" => "Active"
          "permission_name" => "active"
        ]
      ]
      "frozenV2" => array:3 [
        0 => []
        1 => array:1 [
        "type" => "ENERGY"
    ]
        2 => array:1 [
        "type" => "TRON_POWER"
    ]
      ]
      "address" => "410000000000000000000000000000000000000000"
      "balance" => 5000000000
      "create_time" => 1700000000000
      "trc20" => array:1 [
        0 => array:1 [
        "TG3XXyExBkPp9nzdajDZsozEu4BkaSJozs" => "5000000000"
    ]
      ]
      "net_window_size" => 28800000
      "net_window_optimized" => true
    ]
  ]
  "success" => true
  "meta" => array:2 [
        "at" => 1737551574489
    "page_size" => 1
  ]
]*/

        $account = $this->manager->request('v1/accounts/' . $address, [], 'get');

        return $account['data'] ?? [];
    }

    /**
     * Withdraws unfrozen TRX (in Withdrawable status)
     * IMPORTANT: withdrawal is only possible after the lock period (14 days after unfreezing).
     * @param string|null $owner_address Owner address (defaults to the current one)
     * @return array
     * @throws \Exception
     */
    public function withdrawExpireUnfreeze(string $owner_address = null): array
    {
        if($owner_address == null) {
            $owner_address = $this->address['hex'];
        }

        $params = [
            'owner_address' => $owner_address,
        ];

        $withdraw = $this->getManager()->request('wallet/withdrawexpireunfreeze', $params);

        $signedTransaction = $this->signTransaction($withdraw);
        $response = $this->sendRawTransaction($signedTransaction);

        return array_merge($response, $signedTransaction);
    }


}
