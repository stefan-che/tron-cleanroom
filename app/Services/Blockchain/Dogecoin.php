<?php

namespace App\Services\Blockchain;

use App\Services\Blockchain\Services\BlockCypherBasedService;

class Dogecoin extends BlockCypherBasedService
{
    protected string $blockchain = 'doge';
    protected string $network = 'main';
}
