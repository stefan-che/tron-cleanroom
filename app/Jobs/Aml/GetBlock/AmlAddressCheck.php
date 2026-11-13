<?php

namespace App\Jobs\Aml\GetBlock;

use App\Enums\AmlAddressStatuses;
use App\Models\Aml\AmlAddress;
use App\Models\Aml\AmlAddressCheck as AmlAddressCheckModel;
use App\Models\Aml\AmlService;
use App\Repositories\Aml\AmlAddressRepository;
use App\Services\Aml\AmlChecksManager;
use App\Services\Aml\GetBlock\CheckAddress;
use App\Services\Aml\GetBlock\GetResult;
use App\Services\Aml\Services\GetBlockService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AmlAddressCheck implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, Queueable, SerializesModels, InteractsWithQueue;

    protected AmlAddress $amlAddress;

    public $tries = 10;

    public $timeout = 120;

    /**
     * @param AmlAddress $amlAddress
     * @return void
     */
    public function __construct(AmlAddress $amlAddress)
    {
        $this->amlAddress = $amlAddress;
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle()
    {
        $amlService = AmlService::getBlock()->first();

        if (!$amlService) {
            return;
        }

        $getblockCheck = $this->amlAddress->checks()
            ->where('aml_service_id', $amlService->id)
            ->first();

        if ($getblockCheck?->isCompleted()) {
            return;
        }

        $amlAddressRepository = app(AmlAddressRepository::class);

        $amlAddressRepository->update($this->amlAddress, [
            'status' => AmlAddressStatuses::PROCESS->value,
        ]);

        $getblockHash = $getblockCheck?->uid;

        if ($getblockHash) {
            $result = (new GetResult())->getResult($this->amlAddress->id, $getblockHash);

            Log::channel('aml')->info('GetBlock GetResult for address', [
                'address_id' => $this->amlAddress->id,
                'address' => $this->amlAddress->address,
                'hash' => $getblockHash,
                'result' => $result,
            ]);

            if (isset($result['result']['check']['status']) && $result['result']['check']['status'] === 'SUCCESS') {
                $this->processSuccessResult($result, $amlService->id, $getblockCheck);
                return;
            }
        } else {
            $result = (new CheckAddress())->checkAmlAddress($this->amlAddress);

            Log::channel('aml')->info('GetBlock CheckAddress for address', [
                'address_id' => $this->amlAddress->id,
                'address' => $this->amlAddress->address,
                'blockchain_type' => $this->amlAddress->blockchain_type,
                'result' => $result,
            ]);

            if (empty($result)) {
                Log::channel('aml')->warning('GetBlock: unsupported blockchain for address check', [
                    'address_id' => $this->amlAddress->id,
                    'blockchain_type' => $this->amlAddress->blockchain_type,
                ]);
                return;
            }

            if (isset($result['result']['check']['hash']) && $getblockCheck) {
                $getblockCheck->update([
                    'uid' => $result['result']['check']['hash'],
                ]);
            }
        }

        $this->release(1 * 60);
    }

    private function processSuccessResult(array $result, int $amlServiceId, AmlAddressCheckModel $check): void
    {
        $report = $result['result']['check'] ?? [];
        $reportData = $report['report'] ?? [];

        $riskScore = $reportData['riskscore'] ?? null;
        $pdfLink = $report['pdfLink'] ?? '';
        $signals = $reportData['signals'] ?? [];
        $counterparty = $report['counterparty'] ?? [];
        $hash = $result['result']['check']['hash'] ?? null;

        if (is_null($riskScore)) {
            return;
        }

        $check->update([
            'status' => AmlAddressCheckModel::STATUS_COMPLETED,
            'uid' => $hash,
            'aml_risk_score' => $riskScore * GetBlockService::RISC_SCORE_MULTIPLIER,
            'aml_file_report_link' => $pdfLink,
            'response' => $result,
            'signals' => \App\Models\Wallet\Wallet::formatSignals($signals),
            'counterparty' => $counterparty,
            'check_finish_at' => now(),
        ]);

        Log::channel('aml')->info('GetBlock address check completed', [
            'address_id' => $this->amlAddress->id,
            'address' => $this->amlAddress->address,
            'risk_score' => $riskScore,
            'hash' => $hash,
        ]);

        app(AmlChecksManager::class)->checkAndFinalizeAddress($this->amlAddress->fresh());
    }

    public function failed(Throwable $e): void
    {
        app(AmlAddressRepository::class)->update($this->amlAddress, [
            'has_error' => true,
            'error' => Str::limit($e->getMessage(), 2000),
        ]);

        $amlService = AmlService::getBlock()->first();
        if ($amlService) {
            app(AmlChecksManager::class)->failAddressCheck($this->amlAddress, $amlService->id);
            app(AmlChecksManager::class)->checkAndFinalizeAddress($this->amlAddress->fresh());
        }
    }

    public function uniqueId(): string
    {
        return sprintf('aml_address_getblock_check_%s_%s',
            $this->amlAddress->id,
            $this->amlAddress->updated_at?->format('YmdHis')
        );
    }
}
