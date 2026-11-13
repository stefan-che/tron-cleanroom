<?php

namespace App\Services\Blockchain\EVM;

use App\Services\Blockchain\Services\EtherscanV2BasedService;

class Optimism extends EtherscanV2BasedService
{
    protected string $configKey = 'optimism_etherscan';

    protected function getChainId(): int
    {
        return 10; // Optimism Mainnet
    }
}
