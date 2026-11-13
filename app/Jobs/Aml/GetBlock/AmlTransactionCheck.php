<?php

namespace App\Jobs\Aml\GetBlock;

use App\Enums\TransactionStatuses;
use App\Models\Aml\AmlService;
use App\Models\Wallet\Transaction;
use App\Models\Wallet\WalletLog;
use App\Repositories\AmlLogRepository;
use App\Repositories\TransactionRepository;
use App\Services\Aml\AmlChecksManager;
use App\Services\Aml\GetBlock\CheckTx;
use App\Services\Aml\GetBlock\GetResult;
use App\Services\Aml\Services\GetBlockService;
use App\Services\Wallet\WalletLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class AmlTransactionCheck implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, Queueable, InteractsWithQueue;

    /**
     * @var Transaction
     */
    private Transaction $transaction;
    private TransactionRepository $transactionRepository;

    public $tries = 10;

    public $timeout = 120;

    /**
     * @param int $id
     * @return void
     */
    public function __construct(readonly int $id)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->transactionRepository = new TransactionRepository;
        $this->transaction = $this->transactionRepository->getById($this->id);

        $amlService = AmlService::getBlock()->first();
        if (!$amlService) {
            return;
        }

        $getblockCheck = $this->transaction->checks()
            ->where('service_id', $amlService->id)
            ->first();

        if ($getblockCheck?->isCompleted()) {
            return;
        }

        $amlLogRepository = new AmlLogRepository;
        $walletLogger = new WalletLoggerService;

        $getblockHash = $getblockCheck?->uid;

        if ($getblockHash) {
            $result = (new GetResult())->getResultByTransaction($this->transaction, $getblockHash);

            Log::channel('aml')->info('GetBlock GetResult for transaction', [
                'transaction_id' => $this->transaction->id,
                'hash' => $getblockHash,
                'result' => $result,
            ]);

            if (isset($result['result']['check']['status']) && $result['result']['check']['status'] === 'SUCCESS') {
                $this->processSuccessResult($result, $amlService->id);
                return;
            }
        } else {
            $result = (new CheckTx())->checkTxByTransaction($this->transaction);

            Log::channel('aml')->info('GetBlock CheckTx for transaction', [
                'transaction_id' => $this->transaction->id,
                'result' => $result,
            ]);

            $amlLogRepository->store($result, $this->transaction->wallet_id, $amlService->id, $this->transaction->id);
            $walletLogger->log(WalletLog::AML_CHECK, $this->transaction->wallet, array_merge(['service' => $amlService->name], $result));

            if (isset($result['result']['check']['hash']) && $getblockCheck) {
                $getblockCheck->update([
                    'uid' => $result['result']['check']['hash'],
                ]);

                $this->transactionRepository->update($this->transaction, [
                    'status' => TransactionStatuses::PROCESS->value,
                ]);
            }
        }

        $this->release(1 * 60); // Retry in 1 minute to fetch the result
    }

    private function processSuccessResult(array $result, int $amlServiceId): void
    {
        $report = $result['result']['check'] ?? [];
        $reportData = $report['report'] ?? [];

        $riskScore = $reportData['riskscore'] ?? null;
        $pdfLink = $report['pdfLink'] ?? '';
        $signals = $reportData['signals'] ?? [];
        $counterparty = $report['counterparty'] ?? [];

        if (is_null($riskScore)) {
            return;
        }

        $hash = $result['result']['check']['hash'] ?? null;

        $freshTransaction = $this->transaction->fresh();
        $freshTransaction->addCheckToHistory(
            $amlServiceId,
            $riskScore * GetBlockService::RISC_SCORE_MULTIPLIER,
            $pdfLink,
            $hash,
            $result,
            $signals,
            $counterparty,
        );

        app(AmlChecksManager::class)->checkAndFinalizeTransaction($freshTransaction->fresh());
    }

    public function uniqueId(): string
    {
        return 'aml_getblock_transaction_' . $this->id;
    }
}
