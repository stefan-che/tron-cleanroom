<?php
/**
 * php artisan alert-node-stop-block:run
 */

namespace App\Console\Commands\Alerts;

use App\Helpers\Development;
use App\Helpers\TelegramHelper;
use App\Models\Tron\TronNode;
use App\Services\Tron\TronNodeDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AlertNodeStopBlocks extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alert-node-stop-block:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks that blocks on our nodes keep advancing';

    /**
     * How many minutes the block may stay unchanged before sync is considered stopped
     */
    private const int STALL_MINUTES = 3;

    private TronNodeDispatcher $dispatcher;

    public function __construct(
        TronNodeDispatcher $dispatcher,
    )
    {
        parent::__construct();
        $this->dispatcher = $dispatcher;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeStart = microtime(true);

        foreach ($this->dispatcher->getOurNodes() as $node) {
            if ($node->isEnabled()) {
                $this->check($node);
            }
        }

        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time (s): ' . Development::getFormatDiffMicroTime($timeStart));
    }

    private function check(TronNode $node): void
    {
        $currentBlock = $this->dispatcher->getLastBlock($node);

        if (!$currentBlock) {
            $this->warn("Node {$node->name} did not respond");
            return; // Unavailability is handled by alert-node-de-sync-block:run
        }

        $lastBlock = $node->last_block;

        $this->info("Node {$node->name}, last block: {$currentBlock}, previous check: " . ($lastBlock ?: '-'));

        if ($currentBlock !== $lastBlock) {
            // Status is left alone - it is managed by alert-node-de-sync-block:run
            $node->updateLastBlock($currentBlock);

            return;
        }

        $changedAt = $node->last_block_changed_at;

        // Same block, but too little time since the last change - not a stop yet
        if (!$changedAt || $changedAt->gt(now()->subMinutes(self::STALL_MINUTES))) {
            $node->updateLastBlock($currentBlock);

            return;
        }

        $reason = sprintf('Block has not changed for more than %d minutes (since %s). Sync may have stopped.',
            self::STALL_MINUTES, $changedAt->format('d.m.Y H:i'));

        $wasAvailable = $node->is_available;

        $node->markUnavailable($reason, $currentBlock);

        $this->alert("{$node->name}: {$reason}");

        Log::channel('tron')->error('Node sync has stopped', [
            'node' => $node->code,
            'block' => $currentBlock,
        ]);

        if ($wasAvailable) {
            TelegramHelper::sendMessage(sprintf("❌ Node %s is excluded from operations.\n%s", $node->name, $reason));
        }
    }

}
