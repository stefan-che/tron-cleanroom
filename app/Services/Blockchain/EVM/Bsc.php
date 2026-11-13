<?php

namespace App\Services\Blockchain\EVM;

use App\Services\Blockchain\Services\EtherscanV2BasedService;

class Bsc extends EtherscanV2BasedService
{
    protected string $configKey = 'bscscan';

    protected function getChainId(): int
    {
        return 56; // BSC Mainnet
    }
}
