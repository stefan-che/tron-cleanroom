<?php
/**
 * php artisan alert-coinkyt-balance-check:run
 */

namespace App\Console\Commands\Alerts\Aml;

use App\Helpers\Development;
use App\Helpers\TelegramHelper;
use App\Models\Aml\AmlService;
use App\Services\Aml\CoinKyt\GetBalance;
use Illuminate\Console\Command;

class AlertAmlCoinKytBalanceCheck extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alert-coinkyt-balance-check:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    const float BALANCE_ALERT_THRESHOLD = 30;

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

        $amlService = AmlService::coinKyt()->first();
        if (!$amlService || !$amlService->isEnabled()) {
            $this->info('CoinKyt service is disabled, skipping the check.');
            return;
        }

        $balance = (new GetBalance())->get()['current_balance'] ?? null;

        if ($balance === null) {
            $this->info('No CoinKyt balance data, skipping the check.');
            return;
        }

        $this->info('Balance: ' . $balance);

        if ($balance < self::BALANCE_ALERT_THRESHOLD) {
            $txt = sprintf('❌ AML CoinKyt balance (%s) is running low', $balance);
            $this->alert($txt);
            TelegramHelper::sendMessage($txt);
            return;
        }

        $this->info('AML balance is OK');
    }
}
