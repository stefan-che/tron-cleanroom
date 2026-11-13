<?php

namespace App\Services\Blockchain\EVM;

use App\Services\Blockchain\Services\EtherscanV2BasedService;

class Arbitrum extends EtherscanV2BasedService
{
    protected string $configKey = 'arbiscan';

    protected function getChainId(): int
    {
        return 42161; // Arbitrum One
    }
}
