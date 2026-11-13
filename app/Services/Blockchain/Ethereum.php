<?php

namespace App\Services\Blockchain;

use App\Services\Blockchain\Services\EtherscanV2BasedService;

class Ethereum extends EtherscanV2BasedService
{
    protected string $configKey = 'etherscan';

    protected function getChainId(): int
    {
        return 1; // Ethereum Mainnet
    }
}
