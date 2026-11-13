<?php

namespace App\Jobs\Aml\AmlBitOk;

use App\Models\Aml\AmlAddress;
use App\Models\Aml\AmlAddressCheck as AmlAddressCheckModel;
use App\Models\Aml\AmlService;
use App\Models\Wallet\Wallet;
use App\Repositories\Aml\AmlAddressRepository;
use App\Services\Aml\AmlChecksManager;
use App\Services\Aml\AmlBitOk\ManualCheck\CheckAddress;
use App\Services\Aml\AmlBitOk\ManualCheck\Report as ManualCheckReport;
use App\Services\Aml\AmlBitOk\ManualCheck\Result;
use App\Services\Aml\AmlBitOk\ManualCheck\Risks;
use App\Services\Aml\Services\AmlBitOkService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AmlAddressCheck implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, Queueable, SerializesModels, InteractsWithQueue;

    protected AmlAddress $amlAddress;

    public $tries = 10;

    public $timeout = 120;

    public function __construct(AmlAddress $amlAddress)
    {
        $this->amlAddress = $amlAddress;
    }

    public function handle(): void
    {
        $amlService = AmlService::amlBitOk()->first();
        if (!$amlService) {
            return;
        }

        $existingCheck = $this->amlAddress->checks()
            ->where('aml_service_id', $amlService->id)
            ->first();

        if ($this->amlAddress->is_test && $existingCheck) {
            return;
        }

        if ($existingCheck?->isCompleted()) {
            return;
        }

        $network = $this->getNetworkCode($this->amlAddress->blockchain_type);

        if (!$network) {
            Log::channel('aml')->warning('BitOK: Unsupported blockchain', [
                'blockchain_type' => $this->amlAddress->blockchain_type,
            ]);
            return;
        }

        // Step 2: uid already exists - poll for the result
        if ($existingCheck && $existingCheck->uid) {
            $result = (new Result())->getResult($existingCheck->uid);

            Log::channel('aml')->info('BitOK AmlAddressCheck poll', [
                'address' => $this->amlAddress->address,
                'uid' => $existingCheck->uid,
                'result' => $result,
            ]);

            if (isset($result['risk_score']) && $result['risk_score'] !== null) {
                $riskScore = (float) $result['risk_score'] * 100;
                $uid = $existingCheck->uid;

                $bitOkService = new AmlBitOkService();
                $risks = (new Risks())->getRisks($uid);
                $rawSignals = $bitOkService->extractSignals($risks);
                $counterparty = $bitOkService->extractCounterparty($result);

                $formattedSignals = Wallet::formatSignals(
                    $rawSignals,
                    $bitOkService->getSignalGroups(),
                    false,
                );

                $localReportPath = null;
                $reportContent = (new ManualCheckReport())->getReport($uid);
                if (!empty($reportContent['content'])) {
                    $path = "aml_reports/amlbitok/address/{$this->amlAddress->id}.pdf";
                    Storage::disk('public')->put($path, $reportContent['content']);
                    $localReportPath = $path;
                }

                $this->amlAddress->checks()->updateOrCreate(
                    ['aml_service_id' => $amlService->id],
                    [
                        'status' => AmlAddressCheckModel::STATUS_COMPLETED,
                        'uid' => $uid,
                        'aml_risk_score' => $riskScore,
                        'aml_file_report_link' => '',
                        'local_report_path' => $localReportPath,
                        'response' => $result,
                        'signals' => $formattedSignals,
                        'counterparty' => $counterparty,
                        'check_finish_at' => now(),
                    ]
                );

                app(AmlChecksManager::class)->checkAndFinalizeAddress($this->amlAddress->fresh());

                return;
            }

            // Check not finished yet - retry later
            $this->release(2 * 60);
            return;
        }

        // Step 1: submit the address for checking, get a uid
        $initResult = (new CheckAddress())->checkAddress($this->amlAddress->address, $network);

        Log::channel('aml')->info('BitOK AmlAddressCheck init', [
            'address' => $this->amlAddress->address,
            'network' => $network,
            'result' => $initResult,
        ]);

        if (!isset($initResult['id'])) {
            $this->release(2 * 60);
            return;
        }

        $this->amlAddress->checks()->updateOrCreate(
            ['aml_service_id' => $amlService->id],
            [
                'status' => AmlAddressCheckModel::STATUS_PENDING,
                'uid' => $initResult['id'],
                'response' => $initResult,
            ]
        );

        $this->release(2 * 60);
    }

    public function failed(Throwable $e): void
    {
        app(AmlAddressRepository::class)->update($this->amlAddress, [
            'has_error' => true,
            'error' => Str::limit($e->getMessage(), 2000),
        ]);

        $amlService = AmlService::amlBitOk()->first();
        if ($amlService) {
            app(AmlChecksManager::class)->failAddressCheck($this->amlAddress, $amlService->id);
            app(AmlChecksManager::class)->checkAndFinalizeAddress($this->amlAddress->fresh());
        }
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
        return sprintf('aml_address_bitok_check_%s_%s',
            $this->amlAddress->id,
            $this->amlAddress->updated_at?->format('YmdHis')
        );
    }
}
