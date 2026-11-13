<?php

namespace App\Services\Blockchain\Services;

use App\Models\Aml\AmlAddress;
use App\Services\Blockchain\Bitcoin;
use App\Services\Blockchain\BitcoinCash;
use App\Services\Blockchain\Dogecoin;
use App\Services\Blockchain\Ethereum;
use App\Services\Blockchain\EVM\Arbitrum;
use App\Services\Blockchain\EVM\Avalanche;
use App\Services\Blockchain\EVM\Base;
use App\Services\Blockchain\EVM\Bsc;
use App\Services\Blockchain\EVM\Optimism;
use App\Services\Blockchain\EVM\Polygon;
use App\Services\Blockchain\Litecoin;
use App\Services\Blockchain\Solana;
use App\Services\Blockchain\Ton;
use App\Services\Blockchain\Tron;

class BlockchainServiceRegistry
{
    private array $services = [];

    public function __construct()
    {
        $this->initializeServices();
    }

    private function initializeServices(): void
    {
        $this->services = [
            // Main blockchains
            AmlAddress::BLOCKCHAIN_TYPE_TRC_20 => new Tron(),
            AmlAddress::BLOCKCHAIN_TYPE_BTC => new Bitcoin(),
            AmlAddress::BLOCKCHAIN_TYPE_ETH => new Ethereum(),
            AmlAddress::BLOCKCHAIN_TYPE_TON => new Ton(),
            AmlAddress::BLOCKCHAIN_TYPE_SOLANA => new Solana(),

            // EVM-compatible networks
            AmlAddress::BLOCKCHAIN_TYPE_BSC => new Bsc(),
            AmlAddress::BLOCKCHAIN_TYPE_MATIC => new Polygon(),
            AmlAddress::BLOCKCHAIN_TYPE_ARB => new Arbitrum(),
            AmlAddress::BLOCKCHAIN_TYPE_OP => new Optimism(),
            AmlAddress::BLOCKCHAIN_TYPE_BASE => new Base(),
            AmlAddress::BLOCKCHAIN_TYPE_AVAX => new Avalanche(),

            // Bitcoin-like
            AmlAddress::BLOCKCHAIN_TYPE_LTC => new Litecoin(),
            AmlAddress::BLOCKCHAIN_TYPE_DOGE => new Dogecoin(),
            AmlAddress::BLOCKCHAIN_TYPE_BCH => new BitcoinCash(),
        ];
    }

    public function getService(int $blockchainType): ?BlockchainServiceInterface
    {
        return $this->services[$blockchainType] ?? null;
    }

    public function getServiceByName(string $blockchainName): ?BlockchainServiceInterface
    {
        $blockchainType = AmlAddress::getBlockChainTypeByName($blockchainName);

        if ($blockchainType) {
            return $this->getService($blockchainType);
        }

        return null;
    }

    public function isSupported(int $blockchainType): bool
    {
        return isset($this->services[$blockchainType]);
    }
}
