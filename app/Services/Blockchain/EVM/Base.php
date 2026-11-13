<?php

namespace App\Services\Blockchain\EVM;

use App\Services\Blockchain\Services\EtherscanV2BasedService;

class Base extends EtherscanV2BasedService
{
    protected string $configKey = 'basescan';

    protected function getChainId(): int
    {
        return 8453; // Base Mainnet
    }
}
