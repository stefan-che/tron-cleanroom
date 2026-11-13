<?php

namespace App\Services\Blockchain;

use App\Services\Blockchain\Services\AbstractBlockchainService;

/**
 * Bitcoin Cash Service
 *
 * Uses the Blockchair API (free tier: 10,000 req/day)
 * @link https://blockchair.com/api/docs
 */
class BitcoinCash extends AbstractBlockchainService
{
    private const BLOCKCHAIR_API_URL = 'https://api.blockchair.com/bitcoin-cash';

    /**
     * Get transaction count for an address
     */
    public function getTransactionCount(string $address): int
    {
        $url = self::BLOCKCHAIR_API_URL . "/dashboards/address/{$address}";
        $data = $this->makeHttpRequest($url);

        if ($data && isset($data['data'][$address]['address']['transaction_count'])) {
            return (int)$data['data'][$address]['address']['transaction_count'];
        }

        return 0;
    }

    /**
     * Get the latest transaction hash for an address
     */
    public function getLastTransactionHash(string $address): ?string
    {
        $url = self::BLOCKCHAIR_API_URL . "/dashboards/address/{$address}";
        $params = ['limit' => 1, 'offset' => 0];
        
        $data = $this->makeHttpRequest($url, $params);

        // In Blockchair the latest transaction is in the transactions array
        if ($data && isset($data['data'][$address]['transactions']) && !empty($data['data'][$address]['transactions'])) {
            // transactions is an array of hashes, first = newest
            return $data['data'][$address]['transactions'][0] ?? null;
        }

        return null;
    }

    /**
     * Get account info
     */
    public function getAccountInfo(string $address): array
    {
        $url = self::BLOCKCHAIR_API_URL . "/dashboards/address/{$address}";
        $data = $this->makeHttpRequest($url);

        if ($data && isset($data['data'][$address]['address'])) {
            $addressData = $data['data'][$address]['address'];
            return [
                'address' => $address,
                'balance' => $addressData['balance'] ?? 0,
                'transaction_count' => $addressData['transaction_count'] ?? 0,
                'total_received' => $addressData['received'] ?? 0,
                'total_spent' => $addressData['spent'] ?? 0,
            ];
        }

        return [];
    }

    /**
     * Get balance
     */
    public function getBalance(string $address): float
    {
        $url = self::BLOCKCHAIR_API_URL . "/dashboards/address/{$address}";
        $data = $this->makeHttpRequest($url);

        if ($data && isset($data['data'][$address]['address']['balance'])) {
            // Blockchair returns the balance in satoshi
            return (float)$data['data'][$address]['address']['balance'] / 100_000_000;
        }

        return 0.0;
    }
}
