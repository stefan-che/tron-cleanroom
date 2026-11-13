<?php
/**
 * php artisan db:seed --class=TronNodesSeeder
 */

namespace Database\Seeders;

use App\Models\Tron\TronNode;
use Illuminate\Database\Seeder;

class TronNodesSeeder extends Seeder
{
    public function run(): void
    {
        $nodes = [
            [
                'name' => 'Node 1',
                'code' => TronNode::CODE_NODE_1,
                'type' => TronNode::TYPE_OWN,
                'config' => 'node',
                'priority' => 1,
                'description' => 'Our primary node',
            ],
            [
                'name' => 'Node 2',
                'code' => TronNode::CODE_NODE_2,
                'type' => TronNode::TYPE_OWN,
                'config' => 'node_2',
                'priority' => 2,
                'description' => 'Our backup node',
            ],
            [
                'name' => 'TronGrid',
                'code' => TronNode::CODE_TRONGRID,
                'type' => TronNode::TYPE_TRONGRID,
                'config' => 'trongrid',
                'priority' => 100,
                'description' => 'Public trongrid.io API, the fallback when our nodes are unavailable',
            ],
        ];

        foreach ($nodes as $node) {
            $config = config('tron.' . $node['config']);

            if (empty($config['full_node'])) {
                continue;
            }

            // Once created, nodes live in the DB (edited in settings); the seeder does not overwrite them
            TronNode::firstOrCreate(
                ['code' => $node['code']],
                [
                    'name' => $node['name'],
                    'type' => $node['type'],
                    'full_node' => $config['full_node'],
                    'solidity_node' => $config['solidity_node'] ?? null,
                    'event_server' => $config['event_server'] ?? null,
                    'api_key' => $config['api_key'] ?? null,
                    'priority' => $node['priority'],
                    'description' => $node['description'],
                    'is_enabled' => true,
                    'is_available' => true,
                ]
            );
        }
    }
}
