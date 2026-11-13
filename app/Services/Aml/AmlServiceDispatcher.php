<?php

namespace App\Services\Aml;

use App\Enums\WalletStatuses;
use App\Models\Aml\AmlAddress;
use App\Models\Aml\AmlService;
use App\Models\Wallet\Transaction;
use App\Repositories\WalletRepository;
use App\Services\Aml\Contracts\AmlServiceInterface;
use App\Services\Aml\Services\AmlBitOkService;
use App\Services\Aml\Services\AmlBotService;
use App\Services\Aml\Services\CoinKytService;
use App\Services\Aml\Services\GetBlockService;
use App\Services\Aml\Services\WellWalletService;
use App\Services\Blockchain\Services\BlockchainServiceRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AmlServiceDispatcher
{
    /**
     * @var Collection<AmlServiceInterface>
     */
    private Collection $services;

    public function __construct()
    {
        $this->services = collect([
            new AmlBotService(),
            new WellWalletService(),
            new GetBlockService(),
            new AmlBitOkService(),
            new CoinKytService(),
        ]);
    }

    /**
     * Runs the AML check for an address in all active services
     */
    public function dispatchChecks(AmlAddress $amlAddress): void
    {
        // Check whether the address has new transactions
        if (!$this->hasNewTransactions($amlAddress)) {
            Log::channel('aml')->info('No new transactions for address, skipping AML checks', [
                'address' => $amlAddress->address,
                'blockchain_type' => $amlAddress->blockchain_type,
            ]);
            return;
        }

        $activeServices = $this->getActiveServices();

        Log::channel('aml')->info('Starting AML checks for address', [
            'address' => $amlAddress->address,
            'blockchain_type' => $amlAddress->blockchain_type,
            'active_services_count' => $activeServices->count(),
        ]);

        $amlChecksManager = app(AmlChecksManager::class);
        $checkingServiceIds = [];

        /**
         * @var AmlServiceInterface $service
         */
        foreach ($activeServices as $service) {
            $serviceId = AmlService::where('code', $service->getCode())->value('id');

            if ($service->supportsBlockchain($amlAddress->blockchain_type)) {
                if ($serviceId) {
                    $checkingServiceIds[] = $serviceId;
                }
            } else {
                Log::channel('aml')->info('Service does not support blockchain', [
                    'address' => $amlAddress->address,
                    'service' => $service->getCode(),
                    'blockchain_type' => $amlAddress->blockchain_type,
                ]);

                if ($serviceId) {
                    $amlChecksManager->skipAddressCheck($amlAddress, $serviceId);
                }
            }
        }

        if ($checkingServiceIds) {
            $amlChecksManager->initAddressChecks($amlAddress, $checkingServiceIds);
        }

        foreach ($activeServices as $service) {
            if ($service->supportsBlockchain($amlAddress->blockchain_type)) {
                Log::channel('aml')->info('Dispatching check to service', [
                    'address' => $amlAddress->address,
                    'service' => $service->getCode(),
                ]);

                $service->checkAddress($amlAddress);
            }
        }
    }

    /**
     * Runs the AML check for a transaction in all active services
     */
    public function dispatchTransactionChecks(Transaction $transaction): void
    {
        if ($transaction->wallet->isTestWallet()) {
            $transaction->wallet->setFakeAmlResultTransaction();

            app(WalletRepository::class)->update($transaction->wallet, [
                'status' => WalletStatuses::CHECK_TRANSACTION_FINISH->value,
            ]);

            return;
        }

        // Check whether this transaction needs an AML check
        if (!$this->shouldCheckTransaction($transaction)) {
            Log::channel('aml')->info('Transaction does not require AML check', [
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
                'has_existing_risk_score' => !is_null($transaction->aml_risk_score),
            ]);
            return;
        }

        $activeServices = $this->getActiveServices();

        $amlChecksManager = app(AmlChecksManager::class);
        $checkingServiceIds = [];
        $servicesToDispatch = [];

        /**
         * @var AmlServiceInterface $service
         */
        foreach ($activeServices as $service) {
            $serviceId = AmlService::where('code', $service->getCode())->value('id');

            if (!$serviceId) {
                continue;
            }

            $isRecipientSupport = $service->supportsRecipients($transaction->recipient->external_id);
            $isBlockchainSupport = $service->supportsBlockchain($transaction->wallet->blockchain_type);

            if ($isRecipientSupport && $isBlockchainSupport) {
                $checkingServiceIds[] = $serviceId;
                $servicesToDispatch[] = $service;
            } else {
                Log::channel('aml')->info('Service does not support blockchain or recipient for transaction', [
                    'transaction_id' => $transaction->id,
                    'service' => $service->getCode(),
                    'blockchain_type' => $transaction->wallet->blockchain_type,
                    'recipient_external_id' => $transaction->recipient->external_id,
                ]);

                $amlChecksManager->skipTransactionCheck($transaction, $serviceId);
            }
        }

        if ($checkingServiceIds) {
            $amlChecksManager->initTransactionChecks($transaction, $checkingServiceIds);

            app(WalletRepository::class)->update($transaction->wallet, [
                'status' => WalletStatuses::CHECK_TRANSACTION_START->value,
            ]);
        }

        foreach ($servicesToDispatch as $service) {
            Log::channel('aml')->info('Dispatching transaction check to service', [
                'transaction_id' => $transaction->id,
                'wallet_id' => $transaction->wallet_id,
                'amount' => $transaction->amount,
                'service' => $service->getCode(),
            ]);

            $service->checkTransaction($transaction);
        }
    }

    /**
     * Checks the AML status
     */
    public function updateAmlStatusTransaction(Transaction $transaction): void
    {
        if (!$transaction->aml_uid || $transaction->wallet->isTestWallet()) {
            return;
        }

        //todo implement
    }

    /**
     * Downloads the AML report by check ID
     */
    public function downloadReport(string $serviceCode, ?string $directLink, ?array $response): mixed
    {
        $service = $this->getService($serviceCode);

        if (!$service) {
            return response()->json(['error' => 'Service not found'], 404);
        }

        return $service->downloadReport($directLink, $response);
    }

    /**
     * Checks whether the AML address has new transactions
     */
    private function hasNewTransactions(AmlAddress $amlAddress): bool
    {
        $blockchainServiceRegistry = app(BlockchainServiceRegistry::class);
        $blockchainService = $blockchainServiceRegistry->getServiceByName($amlAddress->blockchain_text);

        if (!$blockchainService) {
            Log::channel('aml')->info('Unsupported blockchain for address check', [
                'address' => $amlAddress->address,
                'blockchain' => $amlAddress->blockchain_text,
            ]);
            return true; // Unsupported blockchains are always checked
        }

        $lastHash = $blockchainService->getLastTransactionHash($amlAddress->address);

        Log::channel('aml')->info('Checking for new transactions', [
            'address' => $amlAddress->address,
            'blockchain' => $amlAddress->blockchain_text,
            'last_hash' => $lastHash,
            'current_hash' => $amlAddress->current_transaction_hash,
        ]);

        // If the hash could not be fetched, run the check
        if ($lastHash === null) {
            return true;
        }

        $amlAddress->update(['current_transaction_hash' => $lastHash]);

        return $amlAddress->isNewTransactions();
    }

    /**
     * Checks whether the transaction needs an AML check
     */
    private function shouldCheckTransaction(Transaction $transaction): bool
    {
        // Skip if an AML result already exists
        if (!is_null($transaction->aml_risk_score)) {
            return false;
        }

        return true;

        // Decide by transaction amount
        return $transaction->isAmlCheck();
    }

    /**
     * Returns active AML services from the database
     */
    private function getActiveServices(): Collection
    {
        $activeServiceCodes = AmlService::where('is_enabled', true)
            ->pluck('code')
            ->toArray();

        return $this->services->filter(function (AmlServiceInterface $service) use ($activeServiceCodes) {
            return in_array($service->getCode(), $activeServiceCodes, true);
        });
    }

    /**
     * Returns a service by code
     */
    public function getService(string $code): ?AmlServiceInterface
    {
        return $this->services->first(function (AmlServiceInterface $service) use ($code) {
            return $service->getCode() === $code;
        });
    }

    /**
     * Returns all available services
     */
    public function getAllServices(): Collection
    {
        return $this->services;
    }
}
