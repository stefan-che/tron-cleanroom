<?php

namespace App\Services\Blockchain;

use App\Services\Blockchain\Services\AbstractBlockchainService;

class Bitcoin extends AbstractBlockchainService
{
    private const BLOCKCHAIN_API_BASE_URL = 'https://blockchain.info';
    private const BLOCKCHAIR_API_BASE_URL = 'https://api.blockchair.com/bitcoin';

    /**
     * Get transaction count for an address
     */
    public function getTransactionCount(string $address): int
    {
        try {
            $data = $this->makeHttpRequest(self::BLOCKCHAIN_API_BASE_URL . "/rawaddr/{$address}");

            if ($data && isset($data['n_tx'])) {
                return $data['n_tx'];
            }

            // Fallback: Blockchair API
            return $this->getTransactionCountFromBlockchair($address);

        } catch (\Exception $e) {
            $this->logError(__FUNCTION__, $e->getMessage(), ['address' => $address]);
            return $this->getTransactionCountFromBlockchair($address);
        }
    }

    /**
     * Get the latest transaction hash for an address
     */
    public function getLastTransactionHash(string $address): ?string
    {
        try {
            $data = $this->makeHttpRequest(self::BLOCKCHAIN_API_BASE_URL . "/rawaddr/{$address}");

            if ($data && isset($data['txs']) && !empty($data['txs'])) {
                return $data['txs'][0]['hash'] ?? null;
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
            $data = $this->makeHttpRequest(self::BLOCKCHAIN_API_BASE_URL . "/rawaddr/{$address}");

            if ($data) {
                return [
                    'address' => $address,
                    'balance' => $data['final_balance'] ?? 0,
                    'transaction_count' => $data['n_tx'] ?? 0,
                    'total_received' => $data['total_received'] ?? 0,
                    'total_sent' => $data['total_sent'] ?? 0
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
            $data = $this->makeHttpRequest(self::BLOCKCHAIN_API_BASE_URL . "/rawaddr/{$address}");

            if ($data && isset($data['final_balance'])) {
                return $data['final_balance'] / 100_000_000; // Convert from satoshi to BTC
            }

        } catch (\Exception $e) {
            $this->logError(__FUNCTION__, $e->getMessage(), ['address' => $address]);
        }

        return 0.0;
    }

    /**
     * Get transaction count via the Blockchair API
     */
    private function getTransactionCountFromBlockchair(string $address): int
    {
        try {
            $data = $this->makeHttpRequest(self::BLOCKCHAIR_API_BASE_URL . "/dashboards/address/{$address}");

            if ($data && isset($data['data'][$address]['address']['transaction_count'])) {
                return $data['data'][$address]['address']['transaction_count'];
            }

        } catch (\Exception $e) {
            $this->logError(__FUNCTION__, $e->getMessage(), ['address' => $address]);
        }

        return 0;
    }

    /**
     * Get the latest transaction hash via Blockchair
     */
    private function getLastTransactionHashFromBlockchair(string $address): ?string
    {
        try {
            $data = $this->makeHttpRequest(self::BLOCKCHAIR_API_BASE_URL . "/dashboards/address/{$address}");

            if ($data && isset($data['data'][$address]['address']['last_seen'])) {
                return $data['data'][$address]['address']['last_seen'];
            }

        } catch (\Exception $e) {
            $this->logError(__FUNCTION__, $e->getMessage(), ['address' => $address]);
        }

        return null;
    }
}
