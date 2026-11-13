<?php

namespace App\Services\Blockchain\EVM;

use App\Services\Blockchain\Services\EtherscanV2BasedService;

class Avalanche extends EtherscanV2BasedService
{
    protected string $configKey = 'snowtrace';

    protected function getChainId(): int
    {
        return 43114; // Avalanche C-Chain
    }
}
