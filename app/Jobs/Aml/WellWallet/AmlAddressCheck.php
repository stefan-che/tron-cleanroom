<?php

namespace App\Jobs\Aml\WellWallet;

use App\Models\Aml\AmlAddress;
use App\Models\Aml\AmlAddressCheck as AmlAddressCheckModel;
use App\Models\Aml\AmlService;
use App\Repositories\Aml\AmlAddressRepository;
use App\Models\Wallet\Wallet;
use App\Services\Aml\AmlChecksManager;
use App\Services\Aml\AmlReportStorage;
use App\Services\Aml\Services\WellWalletService;
use App\Services\Aml\WellWallet\CheckWellWalletAddress;
use App\Services\Aml\WellWallet\ReCheck;
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
        $amlService = AmlService::wellWallet()->first();
        if (!$amlService) {
            return;
        }

        $wellWalletCheck = $this->amlAddress->checks()
            ->where('aml_service_id', $amlService->id)
            ->first();

        if ($this->amlAddress->is_test && $wellWalletCheck) {
            return;
        }

        if ($wellWalletCheck?->isCompleted()) {
            return;
        }

        $uid = $wellWalletCheck?->uid;

        if ($uid) {
            // Async check already started - fetch the result by uid
            try {
                $result = (new ReCheck())->reCheck($uid);
            } catch (Throwable $exception) {
                Log::channel('aml')->error('AmlAddressCheck WellWallet recheck exception', [
                    'aml_address_id' => $this->amlAddress->id,
                    'address' => $this->amlAddress->address,
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
                    $this->amlAddress->address,
                    $this->amlAddress->address,
                    $this->amlAddress->well_wallet_network,
                );
            } catch (Throwable $exception) {
                Log::channel('aml')->error('AmlAddressCheck WellWallet check exception', [
                    'aml_address_id' => $this->amlAddress->id,
                    'address' => $this->amlAddress->address,
                    'network' => $this->amlAddress->well_wallet_network,
                    'exception' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ]);

                return;
            }
        }

        Log::channel('aml')->info('AmlAddressCheck WellWallet debug aml ' . $this->amlAddress->address, [
            'address' => $this->amlAddress->address,
            'uid' => $uid,
            'is_recheck' => (bool)$uid,
            'result' => $result,
        ]);

        // Async check started: store the uid to fetch the result on the next attempt
        if (!$uid && ($result['data']['uid'] ?? null)) {
            $this->amlAddress->checks()->updateOrCreate(
                ['aml_service_id' => $amlService->id],
                [
                    'uid' => $result['data']['uid'],
                    'status' => $wellWalletCheck?->status ?? AmlAddressCheckModel::STATUS_PENDING,
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

            $formattedSignals = Wallet::formatSignals(
                $rawSignals,
                $wellWalletService->getSignalGroups(),
                false,
            );

            $check = $this->amlAddress->checks()->updateOrCreate(
                ['aml_service_id' => $amlService->id],
                [
                    'status' => AmlAddressCheckModel::STATUS_COMPLETED,
                    'uid' => $uid ?? $data['uid'] ?? null,
                    'aml_risk_score' => $riscScore,
                    'aml_file_report_link' => $report,
                    'response' => $result,
                    'signals' => $formattedSignals,
                    'counterparty' => $counterparty,
                    'check_finish_at' => now(),
                ]
            );

            $localReportPath = null;
            if ($report) {
                $localReportPath = app(AmlReportStorage::class)->downloadAndStore(
                    $report,
                    "aml_reports/wellwallet/address/{$this->amlAddress->id}.pdf",
                    ['token' => config('services.well_wallet.api_key')],
                );
            }

            if ($localReportPath) {
                $check->update(['local_report_path' => $localReportPath]);
            }

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

        $amlService = AmlService::wellWallet()->first();
        if ($amlService) {
            app(AmlChecksManager::class)->failAddressCheck($this->amlAddress, $amlService->id);
            app(AmlChecksManager::class)->checkAndFinalizeAddress($this->amlAddress->fresh());
        }
    }

    public function uniqueId(): string
    {
        return sprintf('aml_address_well_wallet_check_%s_%s',
            $this->amlAddress->id,
            $this->amlAddress->updated_at?->format('YmdHis')
        );
    }
}
