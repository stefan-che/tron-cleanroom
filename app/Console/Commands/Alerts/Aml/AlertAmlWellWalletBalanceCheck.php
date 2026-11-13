<?php
/**
 * php artisan alert-wellwallet-balance-check:run
 */

namespace App\Console\Commands\Alerts\Aml;

use App\Helpers\Development;
use App\Helpers\TelegramHelper;
use App\Models\Aml\AmlService;
use App\Services\Aml\Services\WellWalletService;
use Illuminate\Console\Command;

class AlertAmlWellWalletBalanceCheck extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alert-wellwallet-balance-check:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    const float BALANCE_ALERT_THRESHOLD = 40;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeStart = microtime(true);

        $this->check();

        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time (s): ' . Development::getFormatDiffMicroTime($timeStart));
    }

    private function check(): void
    {
        if (!Development::isProduction()) {
            return;
        }

        $amlService = AmlService::wellWallet()->first();
        if (!$amlService || !$amlService->isEnabled()) {
            $this->info('WellWallet service is disabled, skipping the check.');
            return;
        }

        $balance = (new WellWalletService())->getChecksBalance();

        if ($balance === null) {
            $this->info('No WellWallet balance data in Redis, skipping the check.');
            return;
        }

        $this->info('Balance: ' . $balance);

        if ($balance < self::BALANCE_ALERT_THRESHOLD) {
            $txt = sprintf('❌ AML WellWallet balance (%s) is running low', $balance);
            $this->alert($txt);
            TelegramHelper::sendMessage($txt);
            return;
        }

        $this->info('AML balance is OK');
    }
}
