<?php

namespace App\Services\Aml\Contracts;

use App\Models\Aml\AmlAddress;

interface AmlServiceInterface
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
     * Checks whether the service supports the given blockchain
     */
    public function supportsBlockchain(int $blockchainType): bool;

    public function supportsRecipients(string $recipientExtCode): bool;

    /**
     * Runs an AML check for an address
     */
    public function checkAddress(AmlAddress $amlAddress): void;

    /**
     * Runs an AML check for a transaction
     */
    public function checkTransaction(\App\Models\Wallet\Transaction $transaction): void;

    /**
     * Downloads the AML report
     */
    public function downloadReport(?string $directLink, ?array $response): mixed;

    /**
     * Returns the list of supported blockchains
     */
    public function getSupportedBlockchains(): array;

    /**
     * Returns signals grouped by risk level: ['low' => [...], 'middle' => [...], 'high' => [...]]
     */
    public function getSignalGroups(): array;
    public static function isEnabledService(): bool;

}
