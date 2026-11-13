<?php

namespace App\Services\Blockchain;

use App\Services\Blockchain\Services\AbstractBlockchainService;

/**
 * TON Blockchain Service
 *
 * @link https://toncenter.com/api/v2/
 */
class Ton extends AbstractBlockchainService
{
    private const string TONCENTER_API_BASE_URL = 'https://toncenter.com/api/v2';

    /**
     * Get transaction count for a TON address
     */
    public function getTransactionCount(string $address): int
    {
        // Fetch transactions
        $url = self::TONCENTER_API_BASE_URL . '/getTransactions';

        $data = $this->makeHttpRequest($url, [
            'address' => $address,
            'limit' => 100
        ]);

        if ($data && isset($data['result']) && is_array($data['result'])) {
            return count($data['result']);
        }

        return 0;
    }

    /**
     * Get the latest transaction hash for a TON address
     */
    public function getLastTransactionHash(string $address): ?string
    {
        $url = self::TONCENTER_API_BASE_URL . '/getAddressInformation';

        $data = $this->makeHttpRequest($url, [
            'address' => $address,
            'limit' => 1
        ]);

        if ($data && !empty($data['result'])) {
            $hash = $data['result']['last_transaction_id']['hash'] ?? '';
            if (!str_contains($hash, 'AAAAAAAAAAA')) {
                return $hash;
            }
        }

        return null;
    }

    /**
     * Get account info
     */
    public function getAccountInfo(string $address): array
    {
        $url = self::TONCENTER_API_BASE_URL . '/getAddressInformation';

        $data = $this->makeHttpRequest($url, [
            'address' => $address
        ]);

        if ($data && isset($data['result'])) {
            return [
                'address' => $address,
                'balance' => $data['result']['balance'] ?? 0,
                'state' => $data['result']['state'] ?? null,
                'last_transaction_id' => $data['result']['last_transaction_id'] ?? null,
            ];
        }

        return [];
    }

    /**
     * Get account balance
     */
    public function getBalance(string $address): float
    {
        $url = self::TONCENTER_API_BASE_URL . '/getAddressBalance';

        $data = $this->makeHttpRequest($url, [
            'address' => $address
        ]);

        if ($data && isset($data['result'])) {
            // Balance is returned in nanotons
            return (float)$data['result'] / 1_000_000_000; // Convert to TON
        }

        return 0.0;
    }
}
