<?php
/**
 * php artisan alert-refee-balance-check:run
 */

namespace App\Console\Commands\Alerts\Energy;

use App\Helpers\Development;
use App\Helpers\TelegramHelper;
use App\Models\Setting;
use App\Services\Refee\Balance;
use Illuminate\Console\Command;

class AlertRefeeBalanceCheck extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alert-refee-balance-check:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';
    protected Balance $balance;

    public function __construct(
        Balance $balance,
    )
    {
        parent::__construct();
        $this->balance = $balance;
    }

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
        $balance = (float)($this->balance->balance()['balance'] ?? 0);

        if ($balance >= 1 && $balance <= Setting::getAlertEnergyMinSumCheck()) {
            $txt = sprintf('❌ Energy balance (%s) is running low, top up in the bot https://t.me/re_fee_bot', $balance);
            $this->alert($txt);
            TelegramHelper::sendMessage($txt);
            return;
        }

        $this->alert('Energy balance is OK');

    }
}
