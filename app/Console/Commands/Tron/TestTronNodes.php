<?php
/**
 * php artisan test:nodes
 * php artisan test:nodes --check
 */

namespace App\Console\Commands\Tron;

use App\Models\Tron\TronNode;
use App\Services\Tron\TronNodeDispatcher;
use Illuminate\Console\Command;

class TestTronNodes extends Command
{
    protected $signature = 'test:nodes {--check : Poll the nodes and update their statuses}';

    protected $description = 'Show TRON nodes, their priorities and statuses';

    private TronNodeDispatcher $dispatcher;

    public function __construct(
        TronNodeDispatcher $dispatcher,
    )
    {
        parent::__construct();
        $this->dispatcher = $dispatcher;
    }

    public function handle(): int
    {
        $nodes = $this->dispatcher->getNodes();

        if ($nodes->isEmpty()) {
            $this->error('No nodes configured, run: php artisan db:seed --class=TronNodesSeeder');

            return self::FAILURE;
        }

        if ($this->option('check')) {
            $this->checkNodes($nodes);
        }

        $this->table(
            ['Priority', 'Name', 'Code', 'Type', 'Address', 'Enabled', 'Available', 'Last block', 'Error'],
            $nodes->map(fn(TronNode $node) => [
                $node->priority,
                $node->name,
                $node->code,
                $node->type,
                $node->full_node,
                $node->is_enabled ? 'yes' : 'no',
                $node->is_available ? 'yes' : 'no',
                $node->last_block ?: '-',
                $node->last_error ?: '-',
            ])->toArray()
        );

        $active = $this->dispatcher->getActiveNode();

        $active
            ? $this->info("Currently using: {$active->name} ({$active->full_node})")
            : $this->error('No available nodes');

        return self::SUCCESS;
    }

    /**
     * @param \Illuminate\Support\Collection<int, TronNode> $nodes
     */
    private function checkNodes($nodes): void
    {
        foreach ($nodes as $node) {
            $lastBlock = $this->dispatcher->getLastBlock($node);

            if (!$lastBlock) {
                $node->markUnavailable('No response from node (manual check)');
                $this->warn("{$node->name}: no response");

                continue;
            }

            $node->markAvailable($lastBlock);
            $this->info("{$node->name}: block {$lastBlock}");
        }

        $this->dispatcher->refresh();
    }
}
