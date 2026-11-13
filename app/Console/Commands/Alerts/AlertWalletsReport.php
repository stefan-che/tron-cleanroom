<?php
/**
 * php artisan alert-wallets:report
 */

namespace App\Console\Commands\Alerts;

use App\Helpers\Development;
use App\Helpers\TelegramHelper;
use App\Models\Recipient\TgUser;
use App\Reports\WalletsReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class AlertWalletsReport extends Command
{
    protected $signature = 'alert-wallets:report';
    protected $description = 'Builds the daily wallets report and sends it to Telegram';

    public function handle(WalletsReport $report)
    {
        if (Development::isLocal()) {
            $message = $report->build();
            TelegramHelper::sendMessage($message);
            return;
        }

        if (!Development::isProduction()) {
            return;
        }

        $timeStart = microtime(true);

        foreach (TgUser::with('recipients')->get() as $tgUser) {
            try {
                if ($tgUser->recipients->isEmpty()) {
                    continue;
                }
                $message = $report->build((int) $tgUser->telegram_id);
                Telegram::sendMessage([
                    'chat_id' => $tgUser->telegram_id,
                    'text' => $message,
                    'parse_mode'   => 'HTML',
                ]);
                sleep(1);
            } catch (\Exception $exception) {
                Log::error('Failed to send AlertWalletsReport message', [
                    'exception' => $exception->getMessage(),
                    'chatId' => $tgUser->telegram_id,
                ]);
            }
        }

        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time (s): ' . Development::getFormatDiffMicroTime($timeStart));
    }
}
