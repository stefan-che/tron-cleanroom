<?php
/**
 * php artisan alert-node-de-sync-block:run
 */

namespace App\Console\Commands\Alerts;

use App\Helpers\Development;
use App\Helpers\MathHelper;
use App\Helpers\TelegramHelper;
use App\Models\Tron\TronNode;
use App\Services\Tron\TronNodeDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AlertNodeDeSyncBlocks extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alert-node-de-sync-block:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks how far our nodes lag behind the blockchain and updates their statuses';

    /**
     * Allowed lag behind the blockchain, in blocks
     */
    private const int DE_SYNC_BLOCKS = 50;

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

        $this->check();

        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time (s): ' . Development::getFormatDiffMicroTime($timeStart));
    }

    private function check(): void
    {
        $tronGridNode = $this->dispatcher->getTronGridNode();

        if (!$tronGridNode) {
            Log::channel('tron')->error('Trongrid node is not configured, nothing to compare blocks with');
            return;
        }

        $lastBlockTronGrid = $this->dispatcher->getLastBlock($tronGridNode);

        if (!$lastBlockTronGrid) {
            $tronGridNode->markUnavailable('No response from trongrid');
            TelegramHelper::sendMessage('❌ Connection problem with trongrid.io');

            return;
        }

        $tronGridNode->markAvailable($lastBlockTronGrid);

        foreach ($this->dispatcher->getOurNodes() as $node) {
            $this->checkNode($node, $lastBlockTronGrid);
        }

        $this->dispatcher->refresh();

        if (!$this->dispatcher->getActiveNode()?->isOur()) {
            TelegramHelper::sendMessage('❌ None of our nodes are available, falling back to trongrid.io');
        }
    }

    private function checkNode(TronNode $node, int $lastBlockTronGrid): void
    {
        if (!$node->isEnabled()) {
            $this->info("Node {$node->name} is disabled, skipping");
            return;
        }

        $lastBlockNode = $this->dispatcher->getLastBlock($node);

        if (!$lastBlockNode) {
            $this->setUnavailable($node, 'Connection problem with the node');
            return;
        }

        $deSyncBlocks = $lastBlockTronGrid - $lastBlockNode;

        if ($deSyncBlocks > self::DE_SYNC_BLOCKS) {
            $this->setUnavailable($node, sprintf('Block desync: last block on chain - %s, on node - %s',
                MathHelper::format($lastBlockTronGrid), MathHelper::format($lastBlockNode)), $lastBlockNode);

            return;
        }

        $this->setAvailable($node, $lastBlockNode);
    }

    /**
     * Marks the node unavailable; posts to Telegram only when the status changes
     */
    private function setUnavailable(TronNode $node, string $reason, ?int $lastBlock = null): void
    {
        $wasAvailable = $node->is_available;

        $node->markUnavailable($reason, $lastBlock);

        $this->warn("{$node->name}: {$reason}");

        Log::channel('tron')->error('Node marked unavailable', [
            'node' => $node->code,
            'reason' => $reason,
        ]);

        if ($wasAvailable) {
            TelegramHelper::sendMessage(sprintf("❌ Node %s is excluded from operations.\n%s", $node->name, $reason));
        }
    }

    /**
     * Returns the node to service; posts to Telegram only when the status changes
     */
    private function setAvailable(TronNode $node, int $lastBlock): void
    {
        $wasAvailable = $node->is_available;

        $node->markAvailable($lastBlock);

        $this->info("Node {$node->name} is OK, last block - {$lastBlock}");

        if (!$wasAvailable) {
            TelegramHelper::sendMessage(sprintf('✅ Node %s is available again and back in service.', $node->name));
        }
    }

}
