<?php

namespace App\Services\Blockchain\Services;

/**
 * Base class for blockchains using the Blockchair API
 * Supports: LTC, DOGE, BCH, BSV and others
 */
abstract class BlockchairBasedService extends AbstractBlockchainService
{
    protected string $blockchainName;
    private const string BLOCKCHAIR_API_BASE_URL = 'https://api.blockchair.com';


    public function getTransactionCount(string $address): int
    {
        try {
            $url = self::BLOCKCHAIR_API_BASE_URL . "/{$this->blockchainName}/dashboards/address/{$address}";
            $data = $this->makeHttpRequest($url);

            if ($data && isset($data['data'][$address]['address']['transaction_count'])) {
                return $data['data'][$address]['address']['transaction_count'];
            }

        } catch (\Exception $e) {
            $this->logError(__FUNCTION__, $e->getMessage(), ['address' => $address]);
        }

        return 0;
    }

    /**
     * Get the latest transaction hash for an address
     */
    public function getLastTransactionHash(string $address): ?string
    {
        try {
            $url = self::BLOCKCHAIR_API_BASE_URL . "/{$this->blockchainName}/dashboards/address/{$address}";
            $params = ['limit' => 1];
            $data = $this->makeHttpRequest($url, $params);

            if ($data && isset($data['data'][$address]['transactions']) && !empty($data['data'][$address]['transactions'])) {
                return $data['data'][$address]['transactions'][0] ?? null;
            }

        } catch (\Exception $e) {
            $this->logError(__FUNCTION__, $e->getMessage(), ['address' => $address]);
        }

        return null;
    }

    /**
     * Get account info
     */
    public function getAccountInfo(string $address): array
    {
        try {
            $url = self::BLOCKCHAIR_API_BASE_URL . "/{$this->blockchainName}/dashboards/address/{$address}";
            $data = $this->makeHttpRequest($url);

            if ($data && isset($data['data'][$address]['address'])) {
                $addressData = $data['data'][$address]['address'];
                return [
                    'address' => $address,
                    'balance' => $addressData['balance'] ?? 0,
                    'transaction_count' => $addressData['transaction_count'] ?? 0,
                    'received' => $addressData['received'] ?? 0,
                    'spent' => $addressData['spent'] ?? 0,
                ];
            }

        } catch (\Exception $e) {
            $this->logError(__FUNCTION__, $e->getMessage(), ['address' => $address]);
        }

        return [];
    }

    /**
     * Get balance
     */
    public function getBalance(string $address): float
    {
        try {
            $url = self::BLOCKCHAIR_API_BASE_URL . "/{$this->blockchainName}/dashboards/address/{$address}";
            $data = $this->makeHttpRequest($url);

            if ($data && isset($data['data'][$address]['address']['balance'])) {
                return $data['data'][$address]['address']['balance'] / 100_000_000; // Convert from satoshi
            }

        } catch (\Exception $e) {
            $this->logError(__FUNCTION__, $e->getMessage(), ['address' => $address]);
        }

        return 0.0;
    }
}

