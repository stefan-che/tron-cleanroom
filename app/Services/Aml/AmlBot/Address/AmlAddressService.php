<?php

namespace App\Services\Aml\AmlBot\Address;

use App\Enums\AmlAddressStatuses;
use App\Models\Aml\AmlAddress;
use App\Repositories\Aml\AmlAddressRepository;
use App\Services\Aml\AmlServiceDispatcher;
use App\Services\Blockchain\Services\BlockchainServiceRegistry;
use Illuminate\Support\Facades\Log;

class AmlAddressService
{

    protected $amlAddressRepository;
    protected $blockchainServiceRegistry;

    public function __construct()
    {
        $this->amlAddressRepository = new AmlAddressRepository();
        $this->blockchainServiceRegistry = new BlockchainServiceRegistry();
    }

    public function check(string $blockchain, string $address, bool $isTest): ?AmlAddress
    {
        return $this->process($blockchain, $address, $isTest);
    }

    private function process(string $blockchain, string $address, bool $isTest): ?AmlAddress
    {
        $amlAddress = $this->amlAddressRepository->getByBlockChainAddress($address, $blockchain);
        $status = $isTest ? AmlAddressStatuses::COMPLETED->value : AmlAddressStatuses::IN_QUEUE->value;

        if ($amlAddress) {
            $this->amlAddressRepository->update($amlAddress, [
                'status' => $status,
                'aml_uid' => null,
                'is_test' => $isTest,
            ]);
        } else {
            $amlAddress = $this->amlAddressRepository->store([
                'address' => $address,
                'blockchain_type' => AmlAddress::getBlockChainTypeByName($blockchain),
                'status' => $status,
                'is_test' => $isTest,
            ]);
        }

        $this->runAml($amlAddress);

        return $amlAddress;
    }

    public function updateCurrentHash(AmlAddress $amlAddress): bool
    {
        try {
            $blockchainService = $this->blockchainServiceRegistry->getService($amlAddress->blockchain_type);

            if (!$blockchainService) {
                Log::error('No blockchain service found', [
                    'blockchain_type' => $amlAddress->blockchain_type,
                    'address' => $amlAddress->address
                ]);
                return false;
            }

            $lastHash = $blockchainService->getLastTransactionHash($amlAddress->address);

            if (!$lastHash) {
                return false;
            }

            return $this->amlAddressRepository->update($amlAddress, [
                'current_transaction_hash' => $lastHash,
            ]);

        } catch (\Exception $exception) {
            Log::error('updateCurrentHash Exception', [
                'address' => $amlAddress->address,
                'blockchain_type' => $amlAddress->blockchain_type,
                'exception' => $exception->getMessage(),
            ]);
        }

        return false;
    }

    public function setStatusNew(AmlAddress $amlAddress): bool
    {
        return $this->amlAddressRepository->update($amlAddress, [
            'status' => AmlAddressStatuses::NEW->value,
        ]);
    }

    public function setStatusCompleted(AmlAddress $amlAddress): bool
    {
        return $this->amlAddressRepository->update($amlAddress, [
            'status' => AmlAddressStatuses::COMPLETED->value,
        ]);
    }


    public function runAml(AmlAddress $amlAddress)
    {
        $dispatcher = app(AmlServiceDispatcher::class);
        $dispatcher->dispatchChecks($amlAddress);
    }
}
