<?php

namespace App\Jobs\Aml\AmlBot;

use App\Enums\AmlAddressStatuses;
use App\Models\Aml\AmlAddress;
use App\Models\Aml\AmlService;
use App\Models\Setting;
use App\Repositories\Aml\AmlAddressRepository;
use App\Services\Aml\AmlBot\CheckAddress;
use App\Services\Aml\AmlBot\ReCheck;
use App\Services\Aml\AmlChecksManager;
use App\Services\Aml\AmlReportStorage;
use App\Services\Aml\Services\AmlBotService;
use App\Services\Blockchain\Services\BlockchainServiceRegistry;
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
        $amlService = AmlService::amlBot()->first();
        if (!$amlService) {
            return;
        }

        $amlBotCheck = $this->amlAddress->checks()
            ->where('aml_service_id', $amlService->id)
            ->first();

        if ($this->amlAddress->is_test && $amlBotCheck) {
            return;
        }

        if ($amlBotCheck?->isCompleted()) {
            return;
        }

        $amlAddressRepository = app(AmlAddressRepository::class);

        if ($amlBotCheck?->uid) {
            $result = (new ReCheck())->reCheck($amlBotCheck->uid);
        } else {
            $result = (new CheckAddress())->check(
                $this->amlAddress->aml_code,
                $this->amlAddress->address,
                Setting::getAmlBotFlowMode(),
            );

            if (($result['data']['uid'] ?? null) && $amlBotCheck) {
                $amlBotCheck->update([
                    'uid' => $result['data']['uid'],
                ]);
            }

            $amlAddressRepository->update($this->amlAddress, [
                'response' => $result,
                'status' => AmlAddressStatuses::PROCESS->value,
            ]);
        }

        Log::channel('aml')->info('AmlAddressCheck debug aml ' . $this->amlAddress->address, [
            'address' => $this->amlAddress->address,
            'result' => $result,
        ]);

        if ($result['result'] ?? false) {
            $riscScore = $result['data']['riskscore'] ?? null;
            $report = $result['data']['pdfReport'] ?? '';
            $status = $result['data']['status'] ?? '';
            $balance = $result['balance'] ?? 0;

            if ($status === 'success') {

                if ($this->amlAddress->isTrc20()) {

                }

                $amlAddressRepository->update($this->amlAddress, [
                    'aml_risk_score' => $riscScore,
                    'aml_file_report_link' => $report,
                    'check_finish_at' => now(),
                    'last_check_hash_at' => now(),
                    'has_error' => false,
                    'error' => '',
                    'aml_balance' => $balance,
                ]);


                $counterparty = (new AmlBotService())->extractCounterparty($result['data'] ?? []);

                $localReportPath = null;
                if ($report) {
                    $localReportPath = app(AmlReportStorage::class)->downloadAndStore(
                        $report,
                        "aml_reports/amlbot/address/{$this->amlAddress->id}.pdf",
                    );
                }

                $freshAddress = $this->amlAddress->fresh();
                $check = $freshAddress->addCheckToHistory($amlService->id, $counterparty);

                if ($localReportPath) {
                    $check->update(['local_report_path' => $localReportPath]);
                }

                app(AmlChecksManager::class)->checkAndFinalizeAddress($freshAddress->fresh());

                return;
            }
        }

        $this->release(2 * 60); // TODO: count attempts and stop if there are too many
    }


    public function failed(Throwable $e): void
    {
        app(AmlAddressRepository::class)->update($this->amlAddress, [
            'has_error' => true,
            'error' => Str::limit($e->getMessage(), 2000),
        ]);

        $amlService = AmlService::amlBot()->first();
        if ($amlService) {
            app(AmlChecksManager::class)->failAddressCheck($this->amlAddress, $amlService->id);
            app(AmlChecksManager::class)->checkAndFinalizeAddress($this->amlAddress->fresh());
        }
    }

    private function saveNoNewCheck(): void
    {
        app(AmlAddressRepository::class)->update($this->amlAddress, [
            'status' => AmlAddressStatuses::NO_NEW_CHECK->value,
        ]);
    }

    public function uniqueId(): string
    {
        return sprintf('aml_address_amlbot_check_%s_%s',
            $this->amlAddress->id,
            $this->amlAddress->updated_at?->format('YmdHis')
        );
    }
}
