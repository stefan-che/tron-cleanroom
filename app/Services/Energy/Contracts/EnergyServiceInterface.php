<?php

namespace App\Services\Energy\Contracts;

use App\Models\Wallet\Wallet;

interface EnergyServiceInterface
{
    /**
     * Service code
     */
    public function getCode(): string;

    /**
     * Service name
     */
    public function getName(): string;

    /**
     * Rents energy for a wallet
     * 
     * @param int $energyAmount Energy amount
     * @param string $receiverAddress Energy receiver address
     * @param string $duration Rental duration (5m, 1h, 1d, 3d, 15d)
     * @return array Rental result ['success' => bool, 'message' => string, 'data' => array]
     */
    public function rentEnergy(int $energyAmount, string $receiverAddress, string $duration = '1h'): array;

    /**
     * Returns the provider balance
     * 
     * @return array ['balance' => float, 'username' => string, ...]
     */
    public function getBalance(): array;

    /**
     * Returns the required energy for addresses
     *
     * @param array $addresses Addresses
     * @return array Required energy per address
     */
    public function getCost(array $addresses): array;

    /**
     * Extracts the rental cost in TRX from the rentEnergy() result.
     *
     * @param array $result Result returned by rentEnergy()
     * @return float Cost in TRX (0 if unknown)
     */
    public function getCostTrxFromRentResult(array $result): float;

    /**
     * Manages Smart Energy (auto top-up) for an address.
     *
     * @param string $target  TRC-20 wallet address
     * @param string $action  start | stop | delete
     * @return array ['success' => bool, 'data' => array]
     */
    public function smartEnergy(string $target, string $action): array;
}

