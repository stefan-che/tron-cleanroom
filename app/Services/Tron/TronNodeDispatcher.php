<?php

namespace App\Services\Tron;

use App\Models\Tron\TronNode;
use IEXBase\TronAPI\Provider\HttpProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Single entry point for working with TRON nodes.
 *
 * All nodes (ours + trongrid.io) live in the tron_nodes table: URLs, API key,
 * priority, whether the node is manually enabled (is_enabled) and actually reachable (is_available).
 * Operations use the first ready node by priority; statuses are set by alerts
 * AlertNodeDeSyncBlocks / AlertNodeStopBlocks.
 */
class TronNodeDispatcher
{
    /**
     * Node request timeout, ms
     */
    private const int TIMEOUT = 30000;

    /**
     * @var Collection<int, TronNode>|null
     */
    private ?Collection $nodes = null;

    /**
     * Established connections by node code
     *
     * @var array<string, Tron>
     */
    private array $connections = [];

    /**
     * All nodes ordered by priority
     *
     * @return Collection<int, TronNode>
     */
    public function getNodes(): Collection
    {
        if ($this->nodes !== null) {
            return $this->nodes;
        }

        try {
            return $this->nodes = TronNode::byPriority()->get();
        } catch (\Throwable $exception) {
            Log::channel('tron')->error('Failed to load node list', [
                'exception' => $exception->getMessage(),
            ]);

            return $this->nodes = collect();
        }
    }

    /**
     * Our nodes (excluding trongrid) ordered by priority
     *
     * @return Collection<int, TronNode>
     */
    public function getOurNodes(): Collection
    {
        return $this->getNodes()->filter(fn(TronNode $node) => $node->isOur())->values();
    }

    /**
     * Current working node: the first enabled and available one by priority
     */
    public function getActiveNode(): ?TronNode
    {
        return $this->getNodes()->first(fn(TronNode $node) => $node->isReady());
    }

    /**
     * trongrid.io node: fallback and block reference for alerts
     */
    public function getTronGridNode(): ?TronNode
    {
        return $this->getNodes()->first(fn(TronNode $node) => $node->isTronGrid());
    }

    public function getNodeByCode(string $code): ?TronNode
    {
        return $this->getNodes()->first(fn(TronNode $node) => $node->code === $code);
    }

    /**
     * Connection to a specific node (cached per request)
     */
    public function connect(TronNode $node): Tron
    {
        if (isset($this->connections[$node->code])) {
            return $this->connections[$node->code];
        }

        $headers = [];

        if ($node->api_key) {
            $headers = [
                'TRON-PRO-API-KEY' => $node->api_key,
            ];
        }

        $fullNode = new HttpProvider($node->full_node, self::TIMEOUT, false, false, $headers);
        $solidityNode = new HttpProvider($node->solidity_node ?: $node->full_node, self::TIMEOUT, false, false, $headers);
        $eventServer = new HttpProvider($node->event_server ?: $node->full_node, self::TIMEOUT, false, false, $headers);

        return $this->connections[$node->code] = new Tron($fullNode, $solidityNode, $eventServer);
    }

    /**
     * Latest block of the node, null if the node did not respond
     */
    public function getLastBlock(TronNode $node): ?int
    {
        try {
            $blocks = $this->connect($node)->getLatestBlocks();
        } catch (\Throwable $exception) {
            Log::channel('tron')->error('Node connection problem', [
                'node' => $node->code,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        return $blocks[0]['block_header']['raw_data']['number'] ?? null;
    }

    /**
     * Reset cached nodes and connections (needed after status changes)
     */
    public function refresh(): void
    {
        $this->nodes = null;
        $this->connections = [];
    }
}
