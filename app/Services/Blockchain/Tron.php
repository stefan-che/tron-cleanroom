<?php

namespace App\Services\Blockchain;

use App\Services\Blockchain\Services\AbstractBlockchainService;
use App\Services\Tron\TronService;

class Tron extends AbstractBlockchainService
{
    private TronService $tronService;

    public function __construct()
    {
        $this->tronService = new TronService();
    }

    /**
     * Get transaction count for an address
     */
    public function getTransactionCount(string $address): int
    {
        try {
            return $this->tronService->getCountTransactions($address);
        } catch (\Exception $e) {
            $this->logError(__FUNCTION__, $e->getMessage(), ['address' => $address]);
            return 0;
        }
    }

    /**
     * Get the latest transaction hash for an address
     */
    public function getLastTransactionHash(string $address): ?string
    {
        try {
            // For TRC-20, get the USDT transaction hash
            $usdtHash = $this->tronService->getLastUsdtTransactionsHash($address);

            // Return the USDT hash if present, otherwise the TRX hash
            return $usdtHash;
            return $usdtHash ?: $trxHash;
        } catch (\Exception $e) {
            $this->logError(__FUNCTION__, $e->getMessage(), ['address' => $address]);
            return null;
        }
    }

    /**
     * Get account info
     */
    public function getAccountInfo(string $address): array
    {
        try {
            $balance = $this->tronService->getBalanceTrx($address);
            $usdtBalance = $this->tronService->getBalanceUsdt($address);

            return [
                'address' => $address,
                'trx_balance' => $balance,
                'usdt_balance' => $usdtBalance,
                'transaction_count' => $this->getTransactionCount($address)
            ];
        } catch (\Exception $e) {
            $this->logError(__FUNCTION__, $e->getMessage(), ['address' => $address]);
            return [];
        }
    }

    /**
     * Get TRX balance
     */
    public function getBalance(string $address): float
    {
        try {
            return $this->tronService->getBalanceTrx($address);
        } catch (\Exception $e) {
            $this->logError(__FUNCTION__, $e->getMessage(), ['address' => $address]);
            return 0.0;
        }
    }
}
