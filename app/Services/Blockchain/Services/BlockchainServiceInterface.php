<?php

namespace App\Services\Blockchain\Services;

interface BlockchainServiceInterface
{
    /**
     * Get transaction count for an address
     */
    public function getTransactionCount(string $address): int;

    /**
     * Get the latest transaction hash for an address
     */
    public function getLastTransactionHash(string $address): ?string;

    /**
     * Check whether there are new transactions
     */
    public function hasNewTransactions(string $address, ?string $lastKnownHash): bool;

    /**
     * Get account info
     */
    public function getAccountInfo(string $address): array;

    /**
     * Get account balance (optional)
     */
    public function getBalance(string $address): float;
}
