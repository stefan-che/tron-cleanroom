<?php

namespace App\Jobs\Aml\WellWallet;

use App\Models\Aml\AmlService;
use App\Models\Wallet\Transaction;
use App\Models\Wallet\TransactionAmlCheck;
use App\Models\Wallet\WalletLog;
use App\Repositories\TransactionRepository;
use App\Services\Aml\AmlChecksManager;
use App\Services\Aml\AmlReportStorage;
use App\Services\Aml\Services\AmlBotService;
use App\Services\Aml\Services\WellWalletService;
use App\Services\Aml\WellWallet\CheckWellWalletAddress;
use App\Services\Aml\WellWallet\ReCheck;
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

        $amlService = AmlService::wellWallet()->first();
        if (!$amlService) {
            return;
        }

        $wellWalletCheck = $this->transaction->checks()
            ->where('service_id', $amlService->id)
            ->first();

        if ($wellWalletCheck?->isCompleted()) {
            return;
        }

        if (!$this->transaction->wallet->isWellWalletCheck()) {
            Log::channel('aml')->info('WellWallet does not support blockchain for transaction', [
                'wallet_id' => $this->transaction->wallet_id,
                'transaction_id' => $this->transaction->id,
                'blockchain_type' => $this->transaction->wallet->blockchain_type,
            ]);
            return;
        }

        $walletLogger = new WalletLoggerService;

        $uid = $wellWalletCheck?->uid;

        if ($uid) {
            // Async check already started - fetch the result by uid
            try {
                $result = (new ReCheck())->reCheck($uid);
            } catch (\Throwable $exception) {
                Log::channel('aml')->error('WellWallet transaction recheck exception', [
                    'wallet_id' => $this->transaction->wallet_id,
                    'transaction_id' => $this->transaction->id,
                    'uid' => $uid,
                    'exception' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ]);

                // Check already paid for - retry fetching the result
                $this->release(1 * 60);

                return;
            }
        } else {
            try {
                $result = (new CheckWellWalletAddress())->check(
                    $this->transaction->from_address,
                    $this->transaction->tx_id,
                    $this->transaction->wallet->well_wallet_network
                );
            } catch (\Throwable $exception) {
                Log::channel('aml')->error('WellWallet transaction check result exception', [
                    'wallet_id' => $this->transaction->wallet_id,
                    'transaction_id' => $this->transaction->id,
                    'exception' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                    'request' => [
                        'address' => $this->transaction->from_address,
                        'tx_id' => $this->transaction->tx_id,
                        'network' => $this->transaction->wallet->well_wallet_network
                    ],
                ]);

                return;
            }
        }

        Log::channel('aml')->info('WellWallet transaction check result', [
            'wallet_id' => $this->transaction->wallet_id,
            'transaction_id' => $this->transaction->id,
            'uid' => $uid,
            'is_recheck' => (bool)$uid,
            'result_code' => $result['code'] ?? null,
            'result' => $result,
            'request' => [
                'address' => $this->transaction->from_address,
                'tx_id' => $this->transaction->tx_id,
                'network' => $this->transaction->wallet->well_wallet_network
            ],
        ]);

        $walletLogger->log(WalletLog::AML_CHECK, $this->transaction->wallet, array_merge(['service' => $amlService->name], $result));

        // Async check started: store the uid to fetch the result on the next attempt
        if (!$uid && ($result['data']['uid'] ?? null)) {
            $this->transaction->checks()->updateOrCreate(
                ['service_id' => $amlService->id],
                [
                    'uid' => $result['data']['uid'],
                    'status' => $wellWalletCheck?->status ?? TransactionAmlCheck::STATUS_PENDING,
                ]
            );
        }

        $data = $result['data']['response'] ?? [];

        $checksBalance = $data['checks_balance'] ?? null;
        if (!is_null($checksBalance)) {
            (new WellWalletService())->setChecksBalance((float)$checksBalance);
        }

        // The result is ready only once aml_score arrives (initial async responses lack it)
        if (!is_null($data['aml_score'] ?? null)) {
            $riscScore = $data['aml_score'];
            $report = $data['pdf_link'] ?? '';

            $wellWalletService = new WellWalletService();
            $rawSignals = $wellWalletService->extractSignals($data['params']['types_of_funding'] ?? []);
            $counterparty = $wellWalletService->extractCounterparty($data['params']['sources_of_funding'] ?? []);

            $freshTransaction = $this->transaction->fresh();

            $check = $freshTransaction->addCheckToHistory(
                $amlService->id,
                $riscScore,
                $report,
                $uid ?? $data['uid'] ?? null,
                $result,
                $rawSignals,
                $counterparty,
                $wellWalletService->getSignalGroups(),
                false,
            );

            $localReportPath = null;
            if ($report) {
                $localReportPath = app(AmlReportStorage::class)->downloadAndStore(
                    $report,
                    "aml_reports/wellwallet/transaction/{$this->id}.pdf",
                    ['token' => config('services.well_wallet.api_key')],
                );
            }

            if ($localReportPath) {
                $check->update(['local_report_path' => $localReportPath]);
            }

            app(AmlChecksManager::class)->checkAndFinalizeTransaction($freshTransaction->fresh());

            if (!AmlBotService::isEnabledService()) {
                $this->transactionRepository->updateAmlRiskScore($this->transaction, $riscScore, $localReportPath);
            }

            return;
        }

        $this->release(1 * 60);
    }

    public function uniqueId(): string
    {
        return 'wellwallet_transaction_' . $this->id;
    }
}
