<?php
/**
 * php artisan test-telegram-bot:run
 */

namespace App\Console\Commands\Alerts;

use App\Helpers\Development;
use App\Helpers\TelegramHelper;
use App\Services\Tron\TronService;
use Illuminate\Console\Command;

class TestTelegramBot extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test-telegram-bot:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeStart = microtime(true);

        TelegramHelper::sendMessage('Hi, this is a test message from the bot ❤️');

        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time (s): ' . Development::getFormatDiffMicroTime($timeStart));
    }

}
