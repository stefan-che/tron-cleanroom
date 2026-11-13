<?php

namespace App\Services\Blockchain\Services;

/**
 * Base class for blockchains using the SoChain API
 *
 * @link https://chain.so/api/
 *
 * Supports: BTC, LTC, DOGE
 * Free tier: 5000 requests/day
 */
abstract class SoChainBasedService extends AbstractBlockchainService
{
    protected string $network; // BTC, LTC, DOGE
    private const SOCHAIN_API_BASE_URL = 'https://chain.so/api/v3';

    /**
     * Get transaction count for an address
     */
    public function getTransactionCount(string $address): int
    {
        $url = self::SOCHAIN_API_BASE_URL . "/transaction_counts/{$this->network}/{$address}";
        $data = $this->makeHttpRequest($url, [], 'GET', $this->getHeaders());

        if ($data && isset($data['data']['total'])) {
            return (int)$data['data']['total'];
        }

        return 0;
    }

    /**
     * Get the latest transaction hash for an address
     */
    public function getLastTransactionHash(string $address): ?string
    {
        $url = self::SOCHAIN_API_BASE_URL . "/transactions/{$this->network}/{$address}/1";
        $data = $this->makeHttpRequest($url, [], 'GET', $this->getHeaders());

        if ($data && isset($data['data']['transactions']) && !empty($data['data']['transactions'])) {
            return $data['data']['transactions'][0]['hash'] ?? null;
        }

        return null;
    }

    /**
     * Get account info
     */
    public function getAccountInfo(string $address): array
    {
        // Fetch balance and transaction counts
        $balanceUrl = self::SOCHAIN_API_BASE_URL . "/balance/{$this->network}/{$address}";
        $countsUrl = self::SOCHAIN_API_BASE_URL . "/transaction_counts/{$this->network}/{$address}";

        $headers = $this->getHeaders();
        $balanceData = $this->makeHttpRequest($balanceUrl, [], 'GET', $headers);
        $countsData = $this->makeHttpRequest($countsUrl, [], 'GET', $headers);

        return [
            'address' => $address,
            'balance' => $balanceData['data']['confirmed'] ?? 0,
            'unconfirmed_balance' => $balanceData['data']['unconfirmed'] ?? 0,
            'transaction_count' => $countsData['data']['total'] ?? 0,
        ];
    }

    /**
     * Get balance
     */
    public function getBalance(string $address): float
    {
        $url = self::SOCHAIN_API_BASE_URL . "/balance/{$this->network}/{$address}";
        $data = $this->makeHttpRequest($url, [], 'GET', $this->getHeaders());

        if ($data && isset($data['data']['confirmed'])) {
            return (float)$data['data']['confirmed'];
        }

        return 0.0;
    }

    /**
     * Get headers for the SoChain API
     */
    private function getHeaders(): array
    {
        $apiKey = $this->getApiKey();

        if ($apiKey) {
            return ['API-KEY' => $apiKey];
        }

        return [];
    }

    /**
     * Get API key (optional)
     */
    private function getApiKey(): string
    {
        return config('services.sochain.api_key', '');
    }
}

