<?php

/**
 * Nodes are stored in the tron_nodes table (addresses, API key, priority, statuses).
 * The values below are seed data for TronNodesSeeder and the fallback connection
 * until the table is populated.
 */
return [
    'node' => [
        'full_node' => env('TRON_FULL_NODE'),
        'solidity_node' => env('TRON_SOLIDITY_NODE'),
        'event_server' => env('TRON_EVENT_SERVER'),
        'api_key' => env('TRON_API_KEY'),
    ],
    'node_2' => [
        'full_node' => env('TRON_2_FULL_NODE'),
        'solidity_node' => env('TRON_2_SOLIDITY_NODE'),
        'event_server' => env('TRON_2_EVENT_SERVER'),
        'api_key' => env('TRON_2_API_KEY'),
    ],
    'trongrid' => [
        'full_node' => env('TRONGRID_FULL_NODE'),
        'solidity_node' => env('TRONGRID_SOLIDITY_NODE'),
        'event_server' => env('TRONGRID_EVENT_SERVER'),
        'api_key' => env('TRONGRID_API_KEY'),
    ],

];
