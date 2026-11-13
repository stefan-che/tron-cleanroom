<?php

namespace App\Jobs\Aml\AmlBitOk;

use App\Models\Aml\AmlAddress;
use App\Models\Aml\AmlService;
use App\Models\Wallet\Transaction;
use App\Models\Wallet\WalletLog;
use App\Repositories\TransactionRepository;
use App\Services\Aml\AmlBitOk\ManualCheck\CheckTransfer;
use App\Services\Aml\AmlBitOk\ManualCheck\Report;
use App\Services\Aml\AmlBitOk\ManualCheck\Result;
use App\Services\Aml\AmlBitOk\ManualCheck\Risks;
use App\Services\Aml\AmlChecksManager;
use App\Services\Aml\Services\AmlBitOkService;
use App\Services\Tron\Tron;
use App\Services\Wallet\WalletLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

        $amlService = AmlService::amlBitOk()->first();
        if (!$amlService) {
            return;
        }

        $existingCheck = $this->transaction->checks()
            ->where('service_id', $amlService->id)
            ->first();

        if ($existingCheck?->isCompleted()) {
            return;
        }

        $network = $this->getNetworkCode($this->transaction->wallet->blockchain_type);

        if (!$network) {
            Log::channel('aml')->info('BitOK: Unsupported blockchain for transaction', [
                'wallet_id' => $this->transaction->wallet_id,
                'transaction_id' => $this->transaction->id,
                'blockchain_type' => $this->transaction->wallet->blockchain_type,
            ]);
            return;
        }

        // Step 2: uid already exists - poll for the transfer result
        if ($existingCheck && $existingCheck->uid) {
            $result = (new Result())->getResult($existingCheck->uid);

            Log::channel('aml')->info('BitOK AmlTransactionCheck poll', [
                'wallet_id' => $this->transaction->wallet_id,
                'transaction_id' => $this->transaction->id,
                'uid' => $existingCheck->uid,
                'result' => $result,
            ]);

            if (isset($result['risk_score']) && $result['risk_score'] !== null) {
                $riskScore = (float) $result['risk_score'] * 100;
                $uid = $existingCheck->uid;
                $reportLink = '';

                $bitOkService = new AmlBitOkService();
                $risks = (new Risks())->getRisks($uid);
                $rawSignals = $bitOkService->extractSignals($risks);
                $counterparty = $bitOkService->extractCounterparty($result);

                $freshTransaction = $this->transaction->fresh();

                $localReportPath = null;
                $reportContent = (new Report())->getReport($uid);
                if (!empty($reportContent['content'])) {
                    $path = "aml_reports/amlbitok/transaction/{$this->id}.pdf";
                    Storage::disk('public')->put($path, $reportContent['content']);
                    $localReportPath = $path;
                }

                $check = $freshTransaction->addCheckToHistory(
                    $amlService->id,
                    $riskScore,
                    $reportLink,
                    $uid,
                    $result,
                    $rawSignals,
                    $counterparty,
                    $bitOkService->getSignalGroups(),
                    false,
                );

                if ($localReportPath) {
                    $check->update(['local_report_path' => $localReportPath]);
                }

                app(AmlChecksManager::class)->checkAndFinalizeTransaction($freshTransaction->fresh());

                return;
            }

            // Transfer still processing - retry later
            $this->release(1 * 60);
            return;
        }

        // Step 1: register the transfer, get a uid
        $walletLogger = new WalletLoggerService;

        $registerResult = (new CheckTransfer())->checkTransfer(
            $this->transaction->tx_id,
            Tron::USDT_TRC20_CONTRACT,
            $this->transaction->wallet->address,
        );

        Log::channel('aml')->info('BitOK AmlTransactionCheck register', [
            'tx_id' => $this->transaction->tx_id,
            'from_address' => $this->transaction->from_address,
            'to_address'=> $this->transaction->wallet->address,
            'direction'=> 'incoming',
            'network' => Tron::USDT_TRC20_CONTRACT,
            'wallet_id' => $this->transaction->wallet_id,
            'transaction_id' => $this->transaction->id,
            'result' => $registerResult,
        ]);

        $walletLogger->log(WalletLog::AML_CHECK, $this->transaction->wallet, array_merge(['service' => $amlService->name], $registerResult));

        if (!isset($registerResult['id'])) {
            $this->release(1 * 60);
            return;
        }

        // Store the uid in the pending record (created by AmlChecksManager::dispatchTransactionChecks)
        if ($existingCheck) {
            $existingCheck->update(['uid' => $registerResult['id']]);
        }

        $this->release(1 * 60);
    }

    private function getNetworkCode(int $blockchainType): ?string
    {
        return match ($blockchainType) {
            AmlAddress::BLOCKCHAIN_TYPE_TRC_20 => 'TRX',
            AmlAddress::BLOCKCHAIN_TYPE_BTC => 'BTC',
            AmlAddress::BLOCKCHAIN_TYPE_ETH => 'ETH',
            AmlAddress::BLOCKCHAIN_TYPE_LTC => 'LTC',
            AmlAddress::BLOCKCHAIN_TYPE_DOGE => 'DOGE',
            AmlAddress::BLOCKCHAIN_TYPE_BSC => 'BSC',
            AmlAddress::BLOCKCHAIN_TYPE_MATIC => 'MATIC',
            default => null,
        };
    }

    public function uniqueId(): string
    {
        return 'aml_bitok_transaction_' . $this->id;
    }
}
