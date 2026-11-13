<?php

namespace App\Jobs\Aml\CoinKyt;

use App\Models\Aml\AmlAddress;
use App\Models\Aml\AmlAddressCheck as AmlAddressCheckModel;
use App\Models\Aml\AmlService;
use App\Models\Wallet\Wallet;
use App\Repositories\Aml\AmlAddressRepository;
use App\Services\Aml\AmlChecksManager;
use App\Services\Aml\AmlReportStorage;
use App\Services\Aml\CoinKyt\CheckAddress;
use App\Services\Aml\CoinKyt\GetReportUrl;
use App\Services\Aml\Services\CoinKytService;
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

    public function __construct(AmlAddress $amlAddress)
    {
        $this->amlAddress = $amlAddress;
    }

    public function handle(): void
    {
        $amlService = AmlService::coinKyt()->first();
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

        $blockchain = $this->getBlockchainCode($this->amlAddress->blockchain_type);

        if (!$blockchain) {
            Log::channel('aml')->warning('CoinKYT: Unsupported blockchain', [
                'blockchain_type' => $this->amlAddress->blockchain_type,
            ]);
            return;
        }

        $result = (new CheckAddress())->check($this->amlAddress->address, $blockchain);

        Log::channel('aml')->info('CoinKYT AmlAddressCheck', [
            'address' => $this->amlAddress->address,
            'blockchain' => $blockchain,
            'result' => $result,
        ]);

        if (isset($result['risk_score'])) {
            // CoinKYT returns risk_score in the 0-1 range; convert to 0-100
            $riskScore = (float) $result['risk_score'] * 100;
            $reportLink = $result['link'] ?? '';
            $uuid = $result['id'] ?? null;

            $coinKytService = new CoinKytService();
            $rawSignals = $coinKytService->extractSignals($result);
            $counterparty = $coinKytService->extractCounterparty($result);

            $formattedSignals = Wallet::formatSignals(
                $rawSignals,
                $coinKytService->getSignalGroups(),
                false,
            );

            $localReportPath = null;
            if ($uuid) {
                $language = config('services.coinkyt.report_language', 'en');
                $urlResult = (new GetReportUrl())->get($uuid, $language);
                if (!empty($urlResult['report_url'])) {
                    $localReportPath = app(AmlReportStorage::class)->downloadAndStore(
                        $urlResult['report_url'],
                        "aml_reports/coinkyt/address/{$this->amlAddress->id}.pdf",
                    );
                }
            }

            $this->amlAddress->checks()->updateOrCreate(
                ['aml_service_id' => $amlService->id],
                [
                    'status' => AmlAddressCheckModel::STATUS_COMPLETED,
                    'aml_risk_score' => $riskScore,
                    'aml_file_report_link' => $reportLink,
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

        $this->release(1 * 60);
    }

    public function failed(Throwable $e): void
    {
        app(AmlAddressRepository::class)->update($this->amlAddress, [
            'has_error' => true,
            'error' => Str::limit($e->getMessage(), 2000),
        ]);

        $amlService = AmlService::coinKyt()->first();
        if ($amlService) {
            app(AmlChecksManager::class)->failAddressCheck($this->amlAddress, $amlService->id);
            app(AmlChecksManager::class)->checkAndFinalizeAddress($this->amlAddress->fresh());
        }
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
        return sprintf('aml_address_coinkyt_check_%s_%s',
            $this->amlAddress->id,
            $this->amlAddress->updated_at?->format('YmdHis')
        );
    }
}
