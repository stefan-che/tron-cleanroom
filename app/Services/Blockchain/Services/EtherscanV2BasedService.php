<?php

namespace App\Services\Blockchain\Services;

use Illuminate\Support\Facades\Http;

/**
 * @link https://etherscan.io/apidashboard
 * @link https://docs.etherscan.io/v2-migration
 * @link https://docs.etherscan.io/supported-chains
 */
abstract class EtherscanV2BasedService extends AbstractBlockchainService
{
    private const string API_V2_ENDPOINT = 'https://api.etherscan.io/v2/api';

    protected string $configKey;

    /**
     * Get transaction count for an address
     */
    public function getTransactionCount(string $address): int
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->get(self::API_V2_ENDPOINT, [
                'chainid' => $this->getChainId(),
                'module' => 'account',
                'action' => 'txlist',
                'address' => $address,
                'startblock' => 0,
                'endblock' => 99999999,
                'page' => 1,
                'offset' => 10000,
                'sort' => 'desc',
                'apikey' => $this->getApiKey()
            ]);

        if ($response->successful()) {
            $data = $response->json();

            if (isset($data['result']) && is_array($data['result'])) {
                return count($data['result']);
            }
        }

        $this->logError(__FUNCTION__, 'Failed to get transaction count', [
            'address' => $address,
            'status' => $response->status(),
            'response' => $response->body()
        ]);

        return 0;
    }

    /**
     * Get the latest transaction hash for an address
     */
    public function getLastTransactionHash(string $address): ?string
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->get(self::API_V2_ENDPOINT, [
                'chainid' => $this->getChainId(),
                'module' => 'account',
                'action' => 'txlist',
                'address' => $address,
                'startblock' => 0,
                'endblock' => 99999999,
                'page' => 1,
                'offset' => 1,
                'sort' => 'desc',
                'apikey' => $this->getApiKey()
            ]);

        if ($response->successful()) {
            $data = $response->json();

            if (isset($data['result']) && is_array($data['result']) && !empty($data['result'])) {
                return $data['result'][0]['hash'] ?? null;
            }
        }

        return null;
    }

    /**
     * Get account info
     */
    public function getAccountInfo(string $address): array
    {
        $balance = $this->getBalance($address);
        $transactionCount = $this->getTransactionCount($address);

        return [
            'address' => $address,
            'balance' => $balance,
            'transaction_count' => $transactionCount,
            'chain_id' => $this->getChainId(),
        ];
    }

    /**
     * Get balance
     */
    public function getBalance(string $address): float
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->get(self::API_V2_ENDPOINT, [
                'chainid' => $this->getChainId(),
                'module' => 'account',
                'action' => 'balance',
                'address' => $address,
                'tag' => 'latest',
                'apikey' => $this->getApiKey()
            ]);

        if ($response->successful()) {
            $data = $response->json();

            if (isset($data['result'])) {
                return (float)$data['result'] / 1e18; // Convert from wei
            }
        }

        return 0.0;
    }

    /**
     * Get the chain ID for a specific blockchain
     */
    abstract protected function getChainId(): int;

    /**
     * Get API key
     */
    protected function getApiKey(): string
    {
        return config("services.{$this->configKey}.api_key", '');
    }
}
