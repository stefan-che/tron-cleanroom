<?php

namespace App\Services\Tron;

use App\Enums\WalletOperationsTypes;
use App\Enums\WalletStatuses;
use App\Models\Setting;
use App\Models\Tron\TronNode;
use App\Models\Wallet\Wallet;
use App\Models\WalletTrx;
use App\Repositories\RecipientRepository;
use App\Repositories\WalletRepository;
use App\Services\Wallet\KeyManager;
use IEXBase\TronAPI\Provider\HttpProvider;
use IEXBase\TronAPI\TRC20Contract;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use App\Jobs\BackupWalletKeyJob;

class TronService
{
    const string STATUS_NAME_SUCCESS = 'SUCCESS';
    const string STATUS_NAME_FAILED = 'FAILED';
    const string STATUS_NAME_OUT_OF_ENERGY = 'OUT_OF_ENERGY';
    const string STATUS_NAME_BANDWITH_ERROR = 'BANDWITH_ERROR';

    /**
     * USDT TRC-20 precision
     */
    const int USDT_DECIMALS = 6;

    protected Tron $tron;
    protected Tron $tronGrid;
    protected TronNodeDispatcher $nodeDispatcher;
    protected ?TronNode $currentNode = null;
    protected TRC20Contract $trc20Contract;
    protected $walletRepository;
    protected $recipientRepository;

    public function __construct(?TronNodeDispatcher $nodeDispatcher = null)
    {
        $this->walletRepository = new WalletRepository;
        $this->recipientRepository = new RecipientRepository();
        $this->nodeDispatcher = $nodeDispatcher ?: app(TronNodeDispatcher::class);

        $this->initTron();
        $this->initTronGrid();
    }

    public function createAccount(): \IEXBase\TronAPI\TronAddress
    {
        return $this->getNode()->createAccount();
    }

    public function generateWallet(
        string  $externalPaymentId,
        string  $externalRecipientId,
        bool    $isTest,
        int     $operationType = WalletOperationsTypes::BUY->value,
        ?string $addressTo = null
    ): ?Wallet
    {
        $recipient = $this->recipientRepository->getByExternalId($externalRecipientId);

        if (!$recipient) {
            return null;
        }

        $connect = $isTest ? Wallet::CRYPTO_CONNECT_4 : Setting::getCryptoConnect();

        $account = $this->createAccount();

        $address = $account->getAddress(true);

        $enc = (new KeyManager())->encrypt($account->getPrivateKey(), $connect);
        $dec = (new KeyManager())->decrypt($enc['key'], $enc['secret'], $enc['salt'], $connect);

        if ($account->getPrivateKey() !== $dec['result']) {
            $this->addLog('method:generateWallet', 'Wallet generation failed', [
                'pk' => $account->getPrivateKey(),
                'enc' => $enc,
                'dec' => $dec,
            ]);

            return null;
        }


        $keyManager = new KeyManager();

        BackupWalletKeyJob::dispatch($address, [
            'key' => $keyManager->encryptBd($address),
            'secret' => $keyManager->encryptBd($account->getPrivateKey()),
            'salt' => $enc['salt'],
        ]);

        return $this->walletRepository->store([
            'private_key' => $enc['key'],
            'private_key_salt' => $enc['salt'],
            'private_key_secret' => $enc['secret'],
            'private_key_hash' => $enc['hash'],
            'address' => $address,
            'address_hex' => $account->getRawData()['address_hex'] ?? '',
            'external_id' => $externalPaymentId,
            'recipient_id' => $recipient->id,
            'blockchain_type' => \App\Models\Recipient\Wallet::BLOCKCHAIN_TYPE_TRC_20,
            'public_key' => $account->getPublicKey(),
            'is_test' => $isTest,
            'connect_type' => $connect,
            'operation_type' => $operationType,
            'address_to' => $operationType === WalletOperationsTypes::BUY->value ? null : $addressTo, // Not stored for incoming (buy) operations
            'saved_key' => Crypt::encryptString($account->getPrivateKey()), // todo: only for key migration
        ]);
    }

    public function sendTrx($fromPrivateKey, $fromAddress, $toAddress, $amount)
    {
        $this->getNode()->setPrivateKey($fromPrivateKey);

        try {
            $this->getNode()->setAddress(trim($fromAddress));

            $transaction = $this->getNode()->sendTransaction(trim($toAddress), $amount);

            return [
                'success' => $transaction['result'] ?? false,
                'message' => hex2bin($transaction['message'] ?? ''),
                'transaction' => $transaction,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function sendTrxFromTrxWallet(WalletTrx $wallet, string $addressTo, float $amount): array
    {
        return $this->sendTrx(
            $wallet->private_key,
            $wallet->address,
            $addressTo,
            $amount
        );
    }

    /**
     * Freezes TRX to obtain resources (energy or bandwidth)
     */
    public function freezeTrx(string $privateKey, string $address, float $amount, string $resourceType = 'BANDWIDTH'): array
    {
        $this->getNode()->setPrivateKey($privateKey);
        $this->getNode()->setAddress(trim($address));

        try {
            $transaction = $this->getNode()->freezeBalance($amount, 3, $resourceType); // 3 days minimum

            return [
                'success' => $transaction['result'] ?? false,
                'message' => hex2bin($transaction['message'] ?? ''),
                'transaction' => $transaction,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Freezes TRX from a TRX wallet
     */
    public function freezeTrxFromTrxWallet(WalletTrx $wallet, float $amount, string $resourceType = 'ENERGY'): array
    {
        return $this->freezeTrx(
            $wallet->private_key,
            $wallet->address,
            $amount,
            $resourceType
        );
    }

    public function getBalanceTrx($address): float
    {
        try {
            $balance = $this->getTronGrid()->getBalance($address, true);
            return $balance;
        } catch (\Throwable $exception) {
            $this->addLog('method:getBalanceTrx', 'Fetching TRX balance via TronGrid', [
                'exception_message' => $exception->getMessage(),
            ]);
        }

        return 0.0;
    }

    public function getBalanceUsdt($address): float
    {
        $contractAddress = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'; // TRON

        $contract = $this->getNode()->contract($contractAddress);
        $this->getNode()->setAddress($address);

        return $contract->balanceOf();
    }

    /**
     * @param Wallet $wallet
     * @param float|null $amount - partial send amount, null - the whole wallet balance
     * @return array
     * @throws \Exception
     */
    public function sendUsdtFromWallet(Wallet $wallet, ?float $amount = null): array
    {
        return $this->sendUsdt(
            $wallet->getDecPrivateKey(),
            $wallet->address,
            $wallet->address_to,
            $amount ?: $wallet->amount
        );
    }

    /**
     * @throws \Exception
     */
    public function sendUsdtFromTrxWallet(WalletTrx $wallet, string $addressTo, float $amount): array
    {
        return $this->sendUsdt(
            $wallet->private_key,
            $wallet->address,
            $addressTo,
            $amount
        );
    }

    /**
     * USDT amount as a fixed-precision string (6 decimals), without scientific notation.
     *
     * @param float $amount
     * @return string
     */
    public static function formatUsdtAmount(float $amount): string
    {
        return number_format(round($amount, self::USDT_DECIMALS), self::USDT_DECIMALS, '.', '');
    }

    public function sendUsdt(string $fromPrivateKey, string $fromAddress, string $toAddress, float $amount): array
    {
        // TRON
        $contractAddress = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

        $tron = $this->getNode();

        $tron->setAddress(trim($fromAddress));
        $tron->setPrivateKey(trim($fromPrivateKey));

        $contract = $tron->contract($contractAddress);

        $contract->setFeeLimit(Setting::getTrxAmount());

        //Pass the amount as a string: TRC20Contract::transfer computes it via bcmul,
        //and a float like 1.0E-5 is cast to scientific notation, which breaks bcmath
        $transaction = $contract->transfer(trim($toAddress), self::formatUsdtAmount($amount));

        if (!isset($transaction['txID'])) {
            throw new \Exception('Failed to create transaction');
        }

        $result = $tron->sendRawTransaction($transaction);

        $this->addLog('method:sendUsdt', 'Signed transaction debug', $result);

        return $result; // ['txid']

        try {

        } catch (\Exception $e) {
            throw new \Exception('USDT send failed: ' . $e->getMessage());
        }
    }

    public function getTransaction($txId)
    {
        return $this->getNode()->getTransaction($txId);
    }

    public function getTrxTransactions(string $address): array
    {
        // TronGrid, not the node: the node does not support this method.
        return $this->getTronGrid()->getTrxTransaction($address);
    }

    public function getAvailableEnergy(string $address): float
    {
        $accountResources = $this->getAccountResources($address);

        $energyLimit = $accountResources['EnergyLimit'] ?? 0;
        $energyUsed = $accountResources['EnergyUsed'] ?? 0;

        return $energyLimit - $energyUsed;
    }

    /**
     * Returns available bandwidth for the address
     * @param string $address
     * @return array ['free' => int, 'staked' => int, 'total' => int]
     */
    public function getAvailableBandwidth(string $address): array
    {
        $accountResources = $this->getAccountResources($address);

        $freeNetLimit = $accountResources['freeNetLimit'] ?? 0;
        $freeNetUsed = $accountResources['freeNetUsed'] ?? 0;
        $freeAvailable = max(0, $freeNetLimit - $freeNetUsed);

        $netLimit = $accountResources['NetLimit'] ?? 0;
        $netUsed = $accountResources['NetUsed'] ?? 0;
        $stakedAvailable = max(0, $netLimit - $netUsed);

        return [
            'free' => $freeAvailable,
            'staked' => $stakedAvailable,
            'total' => $freeAvailable + $stakedAvailable,
            'free_limit' => $freeNetLimit,
            'free_used' => $freeNetUsed,
            'staked_limit' => $netLimit,
            'staked_used' => $netUsed,
        ];
    }

    public function getAccountResources($address)
    {
        /*        array:8 [
                  "freeNetLimit" => 600 // Free bandwidth limit
            "TotalNetLimit" => 43200000000 // Total network bandwidth limit
            "TotalNetWeight" => 38464009261
            "EnergyLimit" => 21006 // Account energy limit
            "TotalEnergyLimit" => 90000000000 // Total network energy limit
            "TotalEnergyWeight" => 7827658932
          ]*/

        return $this->getNode()->getAccountResources($address);
    }

    public function getCountTransactions($address, int $limit = 5): int
    {
        return count($this->getTransactions($address, $limit));
    }


    public function getLastUsdtTransactionsHash($address): ?string
    {
        $tr = $this->getTransactions($address, 100);

        if (empty($tr)) {
            return null;
        }

        foreach ($tr as $transaction) {

            return $transaction['transaction_id'] ?? null;
        }

        return null;
    }

    public function getLastTrxTransactionsHash($address): ?string
    {
        $transactions = $this->getTrxTransactions($address);

        if (empty($transactions)) {
            return null;
        }

        return $transactions[0]['transaction_id'] ?? null;
    }

    public function getTransactions($address, int $limit = 5): array
    {

        $contract = $this->getTronGrid()->contract('');
        $transactions = $contract->getTransactions($address, $limit);

//        0 => array:7 [
//        "transaction_id" => "0000000000000000000000000000000000000000000000000000000000000007"
//      "block_timestamp" => 1700000000000
//      "from" => "TEXAMPLEADDRESS0000000000000000012"
//      "to" => "TEXAMPLEADDRESS0000000000000000013"
//      "value" => "5000000000"
//      "type" => "Transfer"
//      "token_info" => array:4 [
//        "name" => "Tether Token"
//        "symbol" => "USDT"
//        "decimals" => 6
//        "address" => "TG3XXyExBkPp9nzdajDZsozEu4BkaSJozs"
//      ]
//    ]

        return $transactions['data'] ?? [];

    }

    public function statusTransaction(string $txHash): string
    {
        try {
            $data = $this->checkTransaction($txHash);
            return $data['ret'][0]['contractRet'] ?? 'FAILED';
        } catch (\Throwable $exception) {
            $this->addLog('error method:statusTransaction', 'Transaction status check failed', [
                'tx_hash' => $txHash,
                'error' => $exception->getMessage(),
            ]);

            return 'FAILED';
        }
    }

    public function checkTransaction(string $txHash): array
    {
//        array:5 [
//        "ret" => array:1 [
//        0 => array:1 [
//        "contractRet" => "SUCCESS"
//    ]
//  ]
//  "signature" => array:1 [
//        0 => "<signature>"
//    ]
//  "txID" => "0000000000000000000000000000000000000000000000000000000000000008"
//  "raw_data" => array:6 [
//        "contract" => array:1 [
//        0 => array:2 [
//        "parameter" => array:2 [
//        "value" => array:3 [
//        "data" => "a9059cbb000000000000000000000041000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000f4240"
//            "owner_address" => "410000000000000000000000000000000000000000"
//            "contract_address" => "41a614f803b6fd780986a42c78ec9c7f77e6ded13c"
//          ]
//          "type_url" => "type.googleapis.com/protocol.TriggerSmartContract"
//        ]
//        "type" => "TriggerSmartContract"
//      ]
//    ]
//    "ref_block_bytes" => "0000"
//    "ref_block_hash" => "0000000000000000"
//    "expiration" => 1700000060000
//    "fee_limit" => 20000000
//    "timestamp" => 1700000000000
//  ]
//  "raw_data_hex" => "<raw_data_hex>"
//]

        return $this->getNode()->getTransaction($txHash);
    }

    public function getNode(): Tron
    {
        return $this->tron;
    }

    public function getTronGrid(): Tron
    {
        return $this->tronGrid;
    }

    /**
     * Current working node
     */
    public function getCurrentNode(): ?TronNode
    {
        return $this->currentNode;
    }

    public function getNodeDispatcher(): TronNodeDispatcher
    {
        return $this->nodeDispatcher;
    }

    /**
     * Connects to the given node, or to the active one by priority from tron_nodes
     */
    public function initTron(?TronNode $node = null): void
    {
        $node = $node ?: $this->nodeDispatcher->getActiveNode() ?: $this->nodeDispatcher->getTronGridNode();

        if ($node) {
            $this->tron = $this->nodeDispatcher->connect($node);
            $this->currentNode = $node;

            return;
        }

        // Node table not populated yet - use config
        if ($tron = $this->makeFromConfig('trongrid')) {
            $this->tron = $tron;
        }
    }

    /**
     * Fallback connection from config/tron.php while tron_nodes is empty
     */
    private function makeFromConfig(string $key): ?Tron
    {
        if (!config('tron.' . $key . '.full_node')) {
            return null;
        }

        $headers = [];

        if ($apiKey = config('tron.' . $key . '.api_key')) {
            $headers = [
                'TRON-PRO-API-KEY' => $apiKey
            ];
        }

        $fullNode = new HttpProvider(config('tron.' . $key . '.full_node'), 30000, false, false, $headers);
        $solidityNode = new HttpProvider(config('tron.' . $key . '.solidity_node'), 30000, false, false, $headers);
        $eventServer = new HttpProvider(config('tron.' . $key . '.event_server'), 30000, false, false, $headers);

        return new Tron($fullNode, $solidityNode, $eventServer);
    }

    private function initTronGrid(): void
    {
        $node = $this->nodeDispatcher->getTronGridNode();

        if ($node) {
            $this->tronGrid = $this->nodeDispatcher->connect($node);

            return;
        }

        if ($tron = $this->makeFromConfig('trongrid')) {
            $this->tronGrid = $tron;
        }
    }


    public function addLog(string $title, string $message, array $params = []): void
    {
        self::log($title, $message, $params);
    }

    public static function log(string $title, string $message, array $params = []): void
    {
        Log::channel('tron')->info($title, [
            'error' => $message,
            'params' => json_encode($params),
        ]);
    }


    /**
     * Unfreezes TRX to get frozen funds back
     * IMPORTANT: unfreezing is only possible 3 days after freezing.
     * @param string $privateKey Private key
     * @param string $address Wallet address
     * @param float $amount Amount to unfreeze (0 = unfreeze all)
     * @param string $resourceType Resource type: 'ENERGY' or 'BANDWIDTH'
     * @return array
     */
    public function unfreezeTrx(string $privateKey, string $address, float $amount = 0, string $resourceType = 'ENERGY'): array
    {
        $this->getNode()->setPrivateKey($privateKey);
        $this->getNode()->setAddress(trim($address));

        try {
            $transaction = $this->getNode()->unfreezeBalanceV2($amount, $resourceType);

            return [
                'success' => $transaction['result'] ?? false,
                'message' => hex2bin($transaction['message'] ?? ''),
                'transaction' => $transaction,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Unfreezes TRX from a TRX wallet
     *
     * @param WalletTrx $wallet Wallet
     * @param float $amount Amount to unfreeze (0 = unfreeze all)
     * @param string $resourceType Resource type: 'ENERGY' or 'BANDWIDTH'
     * @return array
     */
    public function unfreezeTrxFromTrxWallet(WalletTrx $wallet, float $amount = 0, string $resourceType = 'ENERGY'): array
    {
        return $this->unfreezeTrx(
            $wallet->private_key,
            $wallet->address,
            $amount,
            $resourceType
        );
    }

    /**
     * Withdraws unfrozen TRX (in Withdrawable status) back to the active balance
     * IMPORTANT: withdrawal is only possible after the lock period (14 days after unfreezing).
     * @param string $privateKey Private key
     * @param string $address Wallet address
     * @return array
     */
    public function withdrawExpireUnfreeze(string $privateKey, string $address): array
    {
        $this->getNode()->setPrivateKey($privateKey);
        $this->getNode()->setAddress(trim($address));

        try {
            $transaction = $this->getNode()->withdrawExpireUnfreeze();

            return [
                'success' => $transaction['result'] ?? false,
                'message' => hex2bin($transaction['message'] ?? ''),
                'transaction' => $transaction,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Withdraws unfrozen TRX from a TRX wallet
     *
     * @param WalletTrx $wallet Wallet
     * @return array
     */
    public function withdrawExpireUnfreezeFromTrxWallet(WalletTrx $wallet): array
    {
        return $this->withdrawExpireUnfreeze(
            $wallet->private_key,
            $wallet->address
        );
    }

    /**
     * Checks that the address is actually derived from the private key.
     */
    public function verifyKeyPair(string $privateKeyHex, string $expectedAddressBase58): bool
    {
        $ec = new \Elliptic\EC('secp256k1');
        $priv = $ec->keyFromPrivate($privateKeyHex, 'hex');

        $pubKeyHex = $priv->getPublic(false, 'hex');
        $node = $this->getNode();

        $addressHex = $node->getAddressHex(hex2bin($pubKeyHex));
        $addressBase58 = $node->getBase58CheckAddress(hex2bin($addressHex));

        return hash_equals($expectedAddressBase58, $addressBase58);
    }

}
