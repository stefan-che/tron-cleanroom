<?php

namespace App\Services\Blockchain\EVM;

use App\Services\Blockchain\Services\EtherscanV2BasedService;

class Polygon extends EtherscanV2BasedService
{
    protected string $configKey = 'polygonscan';

    protected function getChainId(): int
    {
        return 137; // Polygon Mainnet
    }
}
