<?php

/**
 * Syncs CoinKYT monitoring notifications.
 *
 * php artisan coinkyt:sync-monitoring
 * php artisan coinkyt:sync-monitoring --limit=500
 */

namespace App\Console\Commands\Aml\CoinKyt;

use App\Services\Aml\CoinKyt\Monitoring\MonitoringService;
use Illuminate\Console\Command;
use Throwable;

class SyncCoinKytMonitoring extends Command
{
    protected $signature = 'coinkyt:sync-monitoring
                            {--limit=100 : Pagination page size (0-1000)}';

    protected $description = 'Sync CoinKYT monitoring notifications';

    public function handle(MonitoringService $service): int
    {
        $limit = (int) $this->option('limit');

        try {
            $created = $service->syncNotifications($limit ?: 100);
        } catch (Throwable $e) {
            $this->error('Sync error: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Sync completed. New events: {$created}");

        return self::SUCCESS;
    }
}
