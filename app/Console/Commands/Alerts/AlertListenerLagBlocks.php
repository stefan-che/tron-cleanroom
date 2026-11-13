<?php
/**
 * php artisan alert-listener-lag-blocks:run
 */

namespace App\Console\Commands\Alerts;

use App\Helpers\Development;
use App\Helpers\TelegramHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlertListenerLagBlocks extends Command
{

    protected $signature = 'alert-listener-lag-blocks:run';

    protected $description = '';

    private const int LAG_THRESHOLD = 20;

    public function handle(): void
    {
        $timeStart = microtime(true);

        $this->check();

        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time (s): ' . Development::getFormatDiffMicroTime($timeStart));
    }

    private function check(): void
    {
        try {
            $url = rtrim(config('services.wallet_listener.url'), '/') . '/health';
            $response = Http::timeout(10)->get($url);
            $data = $response->json();
        } catch (\Throwable $exception) {
            Log::error('AlertListenerLagBlocks: health endpoint request failed', [
                'exception' => $exception->getMessage(),
            ]);

            TelegramHelper::sendMessage('❌ Listener unavailable: no response from the health endpoint. ' . $exception->getMessage());

            return;
        }

        $lagBlocks = $data['lag_blocks'] ?? null;

        if ($lagBlocks === null) {
            Log::error('AlertListenerLagBlocks: lag_blocks field is missing from the response', ['data' => $data]);
            return;
        }

        $this->info("lag_blocks: {$lagBlocks}");

        if ($lagBlocks > self::LAG_THRESHOLD) {
            TelegramHelper::sendMessage(sprintf(
                '❌ Listener is lagging behind the blockchain: lag_blocks = %d (threshold %d). Last block: %s, blockchain: %s',
                $lagBlocks,
                self::LAG_THRESHOLD,
                $data['last_block'] ?? '?',
                $data['latest_chain_block'] ?? '?',
            ));
        }
    }
}
