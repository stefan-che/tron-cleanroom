<?php

namespace App\Services\Aml;

use App\Enums\AmlAddressStatuses;
use App\Enums\TransactionStatuses;
use App\Enums\WalletStatuses;
use App\Jobs\Ai\AiAmlCheckJob;
use App\Models\Setting;
use App\Models\Aml\AmlAddress;
use App\Models\Aml\AmlAddressCheck;
use App\Models\Aml\AmlService;
use App\Models\Wallet\Transaction;
use App\Models\Wallet\TransactionAmlCheck;
use App\Repositories\Aml\AmlAddressRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\WalletRepository;
use Illuminate\Support\Facades\Log;

class AmlChecksManager
{
    /**
     * Create pending records for a transaction before dispatching jobs.
     * Called from AmlServiceDispatcher::dispatchTransactionChecks().
     *
     * @param Transaction $transaction
     * @param int[] $serviceIds
     */
    public function initTransactionChecks(Transaction $transaction, array $serviceIds): void
    {
        foreach ($serviceIds as $serviceId) {
            $transaction->checks()->updateOrCreate(
                ['service_id' => $serviceId],
                ['status' => TransactionAmlCheck::STATUS_PENDING]
            );
        }

        Log::channel('aml')->info('AmlChecksManager: init transaction checks', [
            'transaction_id' => $transaction->id,
            'service_ids' => $serviceIds,
        ]);
    }

    /**
     * Mark a service's transaction check as skipped (blockchain not supported).
     */
    public function skipTransactionCheck(Transaction $transaction, int $serviceId): void
    {
        $transaction->checks()->updateOrCreate(
            ['service_id' => $serviceId],
            ['status' => TransactionAmlCheck::STATUS_SKIPPED]
        );
    }

    /**
     * Mark a service's transaction check as failed.
     */
    public function failTransactionCheck(Transaction $transaction, int $serviceId): void
    {
        $transaction->checks()->updateOrCreate(
            ['service_id' => $serviceId],
            ['status' => TransactionAmlCheck::STATUS_FAILED]
        );
    }

    /**
     * Called by each job after a successful addCheckToHistory().
     * Finalizes the transaction once all services have finished.
     */
    public function checkAndFinalizeTransaction(Transaction $transaction): void
    {
        if (!$this->areAllTransactionChecksDone($transaction)) {
            Log::channel('aml')->info('AmlChecksManager: transaction checks not all done yet', [
                'transaction_id' => $transaction->id,
                'pending_count' => $transaction->checks()->where('status', TransactionAmlCheck::STATUS_PENDING)->count(),
            ]);
            return;
        }

        $this->finalizeTransaction($transaction);
    }

    /**
     * Force-finalize: if at least one service returned a result,
     * finalize now and mark pending records as failed.
     *
     * @return bool - true if finalized
     */
    public function forceCompleteTransaction(Transaction $transaction): bool
    {
        $hasCompleted = $transaction->checks()
            ->where('status', TransactionAmlCheck::STATUS_COMPLETED)
            ->exists();

        if (!$hasCompleted) {
            Log::channel('aml')->warning('AmlChecksManager: force complete failed - no completed checks', [
                'transaction_id' => $transaction->id,
            ]);
            return false;
        }

        // Pending → failed
        $transaction->checks()
            ->where('status', TransactionAmlCheck::STATUS_PENDING)
            ->update(['status' => TransactionAmlCheck::STATUS_FAILED]);

        $this->finalizeTransaction($transaction);

        Log::channel('aml')->info('AmlChecksManager: force complete transaction', [
            'transaction_id' => $transaction->id,
        ]);

        return true;
    }

    /**
     * Check that the transaction has no pending records left.
     */
    private function areAllTransactionChecksDone(Transaction $transaction): bool
    {
        return $transaction->checks()
            ->where('status', TransactionAmlCheck::STATUS_PENDING)
            ->doesntExist();
    }

    /**
     * Finalize a transaction: set the final statuses.
     * aml_risk_score / aml_file_report_link come from AmlBot for backward compatibility,
     * or from the first completed service if AmlBot did not run.
     */
    private function finalizeTransaction(Transaction $transaction): void
    {
        $amlBotServiceId = AmlService::amlBot()->value('id');

        $amlBotCheck = $transaction->checks()
            ->where('status', TransactionAmlCheck::STATUS_COMPLETED)
            ->when($amlBotServiceId, fn ($q) => $q->where('service_id', $amlBotServiceId))
            ->first();

        // Fallback: first completed service
        if (!$amlBotCheck) {
            $amlBotCheck = $transaction->checks()
                ->where('status', TransactionAmlCheck::STATUS_COMPLETED)
                ->first();
        }

        if (!$amlBotCheck) {
            Log::channel('aml')->warning('AmlChecksManager: no completed checks to finalize transaction', [
                'transaction_id' => $transaction->id,
            ]);
            return;
        }

        $riskScore = $amlBotCheck->risk_score;
        $reportLink = $amlBotCheck->file_report_link ?? '';

        $transactionRepository = app(TransactionRepository::class);
        $walletRepository = app(WalletRepository::class);

        $transactionRepository->update($transaction, [
            'status' => TransactionStatuses::FUNDS_VERIFICATION_COMPLETED_SUCCESSFULLY->value,
            'aml_risk_score' => $riskScore,
            'aml_file_report_link' => $reportLink,
        ]);

        // AI review of AML results (async), before the wallet status is finalized.
        // TODO: manual run from the admin panel only for now. Enable automatic run later.
         if (Setting::isAiAmlCheckEnabled() && !$transaction->wallet->isTestWallet()) {
             AiAmlCheckJob::dispatchSync($transaction->wallet->id, $transaction->id);
         }

        $walletRepository->update($transaction->wallet, [
            'aml_risk_score' => $riskScore,
            'aml_file_report_link' => $reportLink,
            'status' => $transaction->wallet->isCheckTransactionStart() ? WalletStatuses::CHECK_TRANSACTION_FINISH->value : $transaction->wallet->status,
        ]);

        Log::channel('aml')->info('AmlChecksManager: transaction finalized', [
            'transaction_id' => $transaction->id,
            'risk_score' => $riskScore,
            'source_service_id' => $amlBotCheck->service_id,
        ]);
    }

    /**
     * Create pending records for an address before dispatching jobs.
     * Called from AmlServiceDispatcher::dispatchChecks().
     *
     * @param AmlAddress $address
     * @param int[] $serviceIds
     */
    public function initAddressChecks(AmlAddress $address, array $serviceIds): void
    {
        foreach ($serviceIds as $serviceId) {
            $address->checks()->updateOrCreate(
                ['aml_service_id' => $serviceId],
                ['status' => AmlAddressCheck::STATUS_PENDING]
            );
        }

        Log::channel('aml')->info('AmlChecksManager: init address checks', [
            'address_id' => $address->id,
            'address' => $address->address,
            'service_ids' => $serviceIds,
        ]);
    }

    /**
     * Mark a service's address check as skipped.
     */
    public function skipAddressCheck(AmlAddress $address, int $serviceId): void
    {
        $address->checks()->updateOrCreate(
            ['aml_service_id' => $serviceId],
            ['status' => AmlAddressCheck::STATUS_SKIPPED]
        );
    }

    /**
     * Mark a service's address check as failed.
     */
    public function failAddressCheck(AmlAddress $address, int $serviceId): void
    {
        $address->checks()->updateOrCreate(
            ['aml_service_id' => $serviceId],
            ['status' => AmlAddressCheck::STATUS_FAILED]
        );
    }

    /**
     * Called by each job after a successful addCheckToHistory() for an address.
     * Finalizes the address once all services have finished.
     */
    public function checkAndFinalizeAddress(AmlAddress $address): void
    {
        if (!$this->areAllAddressChecksDone($address)) {
            Log::channel('aml')->info('AmlChecksManager: address checks not all done yet', [
                'address_id' => $address->id,
                'pending_count' => $address->checks()->where('status', AmlAddressCheck::STATUS_PENDING)->count(),
            ]);
            return;
        }

        $this->finalizeAddress($address);
    }

    /**
     * Force-finalize an address: if at least one service returned a result,
     * finalize now and mark pending records as failed.
     *
     * @return bool - true if finalized
     */
    public function forceCompleteAddress(AmlAddress $address): bool
    {
        $hasCompleted = $address->checks()
            ->where('status', AmlAddressCheck::STATUS_COMPLETED)
            ->exists();

        if (!$hasCompleted) {
            Log::channel('aml')->warning('AmlChecksManager: force complete address failed - no completed checks', [
                'address_id' => $address->id,
            ]);
            return false;
        }

        // Pending → failed
        $address->checks()
            ->where('status', AmlAddressCheck::STATUS_PENDING)
            ->update(['status' => AmlAddressCheck::STATUS_FAILED]);

        $this->finalizeAddress($address);

        Log::channel('aml')->info('AmlChecksManager: force complete address', [
            'address_id' => $address->id,
        ]);

        return true;
    }

    /**
     * Check that the address has no pending records left.
     */
    private function areAllAddressChecksDone(AmlAddress $address): bool
    {
        return $address->checks()
            ->where('status', AmlAddressCheck::STATUS_PENDING)
            ->doesntExist();
    }

    /**
     * Finalize an address: update status, aml_risk_score, check_finish_at.
     * Data comes from AmlBot, falling back to the first completed service.
     */
    private function finalizeAddress(AmlAddress $address): void
    {
        $amlBotServiceId = AmlService::amlBot()->value('id');

        $amlBotCheck = $address->checks()
            ->where('status', AmlAddressCheck::STATUS_COMPLETED)
            ->when($amlBotServiceId, fn ($q) => $q->where('aml_service_id', $amlBotServiceId))
            ->first();

        if (!$amlBotCheck) {
            $amlBotCheck = $address->checks()
                ->where('status', AmlAddressCheck::STATUS_COMPLETED)
                ->first();
        }

        if (!$amlBotCheck) {
            Log::channel('aml')->warning('AmlChecksManager: no completed checks to finalize address', [
                'address_id' => $address->id,
            ]);
            return;
        }

        app(AmlAddressRepository::class)->update($address, [
            'status' => AmlAddressStatuses::COMPLETED->value,
            'aml_risk_score' => $amlBotCheck->aml_risk_score,
            'aml_file_report_link' => $amlBotCheck->aml_file_report_link ?? '',
            'check_finish_at' => now(),
            'last_check_hash_at' => now(),
            'has_error' => false,
            'error' => '',
        ]);

        Log::channel('aml')->info('AmlChecksManager: address finalized', [
            'address_id' => $address->id,
            'risk_score' => $amlBotCheck->aml_risk_score,
            'source_service_id' => $amlBotCheck->aml_service_id,
        ]);
    }
}
