<?php

namespace App\Jobs\Aml\CoinKyt;

use App\Models\Aml\AmlAddress;
use App\Models\Aml\AmlService;
use App\Models\Wallet\Transaction;
use App\Models\Wallet\WalletLog;
use App\Repositories\TransactionRepository;
use App\Services\Aml\AmlChecksManager;
use App\Services\Aml\AmlReportStorage;
use App\Services\Aml\CoinKyt\CheckTransaction;
use App\Services\Aml\CoinKyt\GetReportUrl;
use App\Services\Aml\Services\CoinKytService;
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

    private Transaction $transaction;
    private TransactionRepository $transactionRepository;

    public $tries = 10;

    public $timeout = 120;

    public function __construct(readonly int $id)
    {
    }

    public function handle(): void
    {
        $this->transactionRepository = new TransactionRepository;
        $this->transaction = $this->transactionRepository->getById($this->id);

        $amlService = AmlService::coinKyt()->first();
        if (!$amlService) {
            return;
        }

        $existingCheck = $this->transaction->checks()
            ->where('service_id', $amlService->id)
            ->first();

        if ($existingCheck?->isCompleted()) {
            return;
        }

        $blockchain = $this->getBlockchainCode($this->transaction->wallet->blockchain_type);

        if (!$blockchain) {
            Log::channel('aml')->info('CoinKYT: Unsupported blockchain for transaction', [
                'wallet_id' => $this->transaction->wallet_id,
                'transaction_id' => $this->transaction->id,
                'blockchain_type' => $this->transaction->wallet->blockchain_type,
            ]);
            return;
        }

        $walletLogger = new WalletLoggerService;

        $result = (new CheckTransaction())->check($this->transaction->tx_id, $blockchain);

        Log::channel('aml')->info('CoinKYT AmlTransactionCheck', [
            'wallet_id' => $this->transaction->wallet_id,
            'transaction_id' => $this->transaction->id,
            'tx_id' => $this->transaction->tx_id,
            'blockchain' => $blockchain,
            'result' => $result,
        ]);

        $walletLogger->log(WalletLog::AML_CHECK, $this->transaction->wallet, array_merge(['service' => $amlService->name], $result));

        if (isset($result['risk_score'])) {
            // CoinKYT returns risk_score in the 0-1 range; convert to 0-100
            $riskScore = (float) $result['risk_score'] * 100;
            $reportLink = $result['link'] ?? '';
            $uuid = $result['id'] ?? null;

            $coinKytService = new CoinKytService();
            $rawSignals = $coinKytService->extractSignals($result);
            $counterparty = $coinKytService->extractCounterparty($result);

            $freshTransaction = $this->transaction->fresh();

            $localReportPath = null;
            if ($uuid) {
                $language = config('services.coinkyt.report_language', 'en');
                $urlResult = (new GetReportUrl())->get($uuid, $language);
                if (!empty($urlResult['report_url'])) {
                    $localReportPath = app(AmlReportStorage::class)->downloadAndStore(
                        $urlResult['report_url'],
                        "aml_reports/coinkyt/transaction/{$this->id}.pdf",
                    );
                }
            }

            $check = $freshTransaction->addCheckToHistory(
                $amlService->id,
                $riskScore,
                $reportLink,
                $uuid,
                $result,
                $rawSignals,
                $counterparty,
                $coinKytService->getSignalGroups(),
                false,
            );

            if ($localReportPath) {
                $check->update(['local_report_path' => $localReportPath]);
            }

            app(AmlChecksManager::class)->checkAndFinalizeTransaction($freshTransaction->fresh());

            return;
        }

        $this->release(1 * 60);
    }

    /**
     * @todo move to a shared class
     * @param int $blockchainType
     * @return string|null
     */
    private function getBlockchainCode(int $blockchainType): ?string
    {
        return match ($blockchainType) {
            AmlAddress::BLOCKCHAIN_TYPE_TRC_20 => 'trx',
            AmlAddress::BLOCKCHAIN_TYPE_BTC => 'btc',
            AmlAddress::BLOCKCHAIN_TYPE_ETH => 'eth',
            default => null,
        };
    }

    public function uniqueId(): string
    {
        return 'aml_coinkyt_transaction_' . $this->id;
    }
}
