<?php

namespace App\Services\Blockchain;

use App\Services\Blockchain\Services\BlockCypherBasedService;

class Litecoin extends BlockCypherBasedService
{
    protected string $blockchain = 'ltc';
    protected string $network = 'main';
}
