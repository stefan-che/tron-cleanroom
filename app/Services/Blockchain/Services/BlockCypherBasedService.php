<?php

namespace App\Services\Blockchain\Services;

/**
 * Base class for blockchains using the BlockCypher API
 *
 * @link https://www.blockcypher.com/dev/
 *
 * Supports: BTC, LTC, DOGE
 * Free tier: 200 req/hour (no token), 3000 req/hour (with token)
 * Free token: https://accounts.blockcypher.com/
 */
abstract class BlockCypherBasedService extends AbstractBlockchainService
{
    protected string $blockchain; // btc, ltc, doge
    protected string $network = 'main'; // main or test3
    private const BLOCKCYPHER_API_BASE_URL = 'https://api.blockcypher.com/v1';

    /**
     * Get transaction count for an address
     */
    public function getTransactionCount(string $address): int
    {
        $url = self::BLOCKCYPHER_API_BASE_URL . "/{$this->blockchain}/{$this->network}/addrs/{$address}";
        $params = $this->getApiParams();

        $data = $this->makeHttpRequest($url, $params);

        if ($data && isset($data['n_tx'])) {
            return (int)$data['n_tx'];
        }

        return 0;
    }

    /**
     * Get the latest transaction hash for an address
     */
    public function getLastTransactionHash(string $address): ?string
    {
        $url = self::BLOCKCYPHER_API_BASE_URL . "/{$this->blockchain}/{$this->network}/addrs/{$address}";
        $params = array_merge($this->getApiParams(), ['limit' => 1]);

        $data = $this->makeHttpRequest($url, $params);

        if ($data && isset($data['txrefs']) && !empty($data['txrefs'])) {
            return $data['txrefs'][0]['tx_hash'] ?? null;
        }

        // For addresses without unconfirmed transactions, check confirmed ones
        if ($data && isset($data['unconfirmed_txrefs']) && !empty($data['unconfirmed_txrefs'])) {
            return $data['unconfirmed_txrefs'][0]['tx_hash'] ?? null;
        }

        return null;
    }

    /**
     * Get account info
     */
    public function getAccountInfo(string $address): array
    {
        $url = self::BLOCKCYPHER_API_BASE_URL . "/{$this->blockchain}/{$this->network}/addrs/{$address}";
        $params = $this->getApiParams();

        $data = $this->makeHttpRequest($url, $params);

        if ($data) {
            return [
                'address' => $address,
                'balance' => $data['balance'] ?? 0,
                'unconfirmed_balance' => $data['unconfirmed_balance'] ?? 0,
                'final_balance' => $data['final_balance'] ?? 0,
                'transaction_count' => $data['n_tx'] ?? 0,
                'total_received' => $data['total_received'] ?? 0,
                'total_sent' => $data['total_sent'] ?? 0,
            ];
        }

        return [];
    }

    /**
     * Get balance
     */
    public function getBalance(string $address): float
    {
        $url = self::BLOCKCYPHER_API_BASE_URL . "/{$this->blockchain}/{$this->network}/addrs/{$address}/balance";
        $params = $this->getApiParams();

        $data = $this->makeHttpRequest($url, $params);

        if ($data && isset($data['balance'])) {
            // BlockCypher returns the balance in satoshi (for BTC/LTC/DOGE)
            return (float)$data['balance'] / 100_000_000;
        }

        return 0.0;
    }

    /**
     * Get API params (with token if set)
     */
    private function getApiParams(): array
    {
        $token = $this->getApiToken();

        if ($token) {
            return ['token' => $token];
        }

        return [];
    }

    /**
     * Get API token (optional but recommended)
     */
    private function getApiToken(): string
    {
        return config('services.blockcypher.token', '');
    }
}

