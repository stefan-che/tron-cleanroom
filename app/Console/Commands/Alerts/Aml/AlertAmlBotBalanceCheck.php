<?php
/**
 * php artisan alert-amlbot-balance-check:run
 */

namespace App\Console\Commands\Alerts\Aml;

use App\Helpers\Development;
use App\Helpers\TelegramHelper;
use App\Services\Aml\AmlBot\History;
use Illuminate\Console\Command;

class AlertAmlBotBalanceCheck extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alert-amlbot-balance-check:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';
    protected History $balance;

    public function __construct(
        History $balance,
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
        if (!Development::isProduction()) {
            return;
        }

        $balance = (float)($this->balance->history()['balance'] ?? 0);

        $this->info('Balance: '. $balance);

        if ($balance > 0 && $balance <= 400) {
            $txt = sprintf('❌ AML AMLBot balance (%s) is running low', $balance);
            $this->alert($txt);
            TelegramHelper::sendMessage($txt);
            return;
        }

        $this->alert('AML balance is OK');

    }
}
