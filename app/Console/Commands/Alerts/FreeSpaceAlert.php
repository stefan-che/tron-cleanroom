<?php
/**
 * php artisan alert-free-space:run
 * php artisan alert-free-space:run --threshold=15
 */

namespace App\Console\Commands\Alerts;

use App\Helpers\ArrayHelper;
use App\Helpers\Development;
use App\Helpers\TelegramHelper;
use Illuminate\Console\Command;

class FreeSpaceAlert extends Command
{
    protected $signature = 'alert-free-space:run {--threshold= : Minimum percentage of free space}';
    protected $description = '';

    /**
     * Minimum percentage of free disk space.
     */
    private float $minimumFreeSpacePercent = 20;

    public function handle()
    {
        $timeStart = microtime(true);

        $this->process();

        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time (s): ' . Development::getFormatDiffMicroTime($timeStart));
    }

    private function process()
    {
        $threshold = $this->getThreshold();

        $freeSpace = disk_free_space('/');
        $totalSpace = disk_total_space('/');

        if ($freeSpace === false || $totalSpace === false || $totalSpace <= 0) {
            $this->error('Failed to get free disk space information!');
            return;
        }

        $freePercent = $freeSpace / $totalSpace * 100;
        $freeSpaceGb = $freeSpace / Development::byteToGb();
        $totalSpaceGb = $totalSpace / Development::byteToGb();

        $info = round($freePercent, 2) . '% (' . round($freeSpaceGb, 2) . ' GB of ' . round($totalSpaceGb, 2) . ' GB)';

        if ($freePercent < $threshold) {
            $message = [
                '⚠️ Warning! Disk space is running low' => "\n",
                'Current free space: ' => $info,
                'Minimum required percentage: ' => round($threshold, 2) . '%',
            ];

            TelegramHelper::sendMessage(ArrayHelper::implodeWithKeys($message, ' '));

            $this->error('Not enough free disk space!');
        } else {
            $this->info('Enough free disk space: ' . $info);
        }
    }

    private function getThreshold(): float
    {
        $threshold = $this->option('threshold');

        if ($threshold === null || !is_numeric($threshold)) {
            return $this->minimumFreeSpacePercent;
        }

        return max(0, min(100, (float)$threshold));
    }
}
