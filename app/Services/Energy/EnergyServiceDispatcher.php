<?php

namespace App\Services\Energy;

use App\Models\Energy\EnergyService;
use App\Models\Wallet\Wallet;
use App\Services\Energy\Contracts\EnergyServiceInterface;
use App\Services\Energy\Services\RefeeService;
use App\Services\Energy\Services\TrEnergyService;
use App\Services\Energy\Services\NettsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class EnergyServiceDispatcher
{
    /**
     * @var Collection<EnergyServiceInterface>
     */
    private Collection $services;

    public function __construct()
    {
        $this->services = collect([
            new RefeeService(),
            new TrEnergyService(),
            new NettsService(),
        ]);
    }

    /**
     * Rents energy via the active provider
     *
     * @param int $energyAmount Energy amount
     * @param string $receiverAddress Energy receiver address
     * @param string $duration Rental duration (5m, 1h, 1d, 3d, 15d)
     * @param Wallet|null $wallet Optional wallet for logging
     * @return array Rental result
     */
    public function rentEnergy(int $energyAmount, string $receiverAddress, string $duration = '1h', ?Wallet $wallet = null): array
    {
        if ($wallet && $wallet->isTestWallet()) {
            return [
                'success' => false,
                'message' => 'Test wallet does not require energy rental',
            ];
        }

        $activeService = $this->getActiveService();

        if (!$activeService) {
            Log::channel('energy')->warning('No active energy service found', [
                'wallet_id' => $wallet?->id,
            ]);

            return [
                'success' => false,
                'message' => 'No active energy provider',
            ];
        }

        Log::channel('energy')->info('Renting energy through service', [
            'wallet_id' => $wallet?->id,
            'wallet_address' => $wallet?->address,
            'energy_amount' => $energyAmount,
            'receiver_address' => $receiverAddress,
            'duration' => $duration,
            'service' => $activeService->getCode(),
        ]);

        try {
            $result = $activeService->rentEnergy($energyAmount, $receiverAddress, $duration);

            if ($result['success']) {
                $result['cost_trx'] = $activeService->getCostTrxFromRentResult($result);

                Log::channel('energy')->info('Energy rented successfully', [
                    'wallet_id' => $wallet?->id,
                    'energy_amount' => $energyAmount,
                    'service' => $activeService->getCode(),
                    'cost_trx' => $result['cost_trx'],
                    'result' => $result,
                ]);
            } else {
                Log::channel('energy')->error('Failed to rent energy', [
                    'wallet_id' => $wallet?->id,
                    'energy_amount' => $energyAmount,
                    'service' => $activeService->getCode(),
                    'error' => $result['message'] ?? 'Unknown error',
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            Log::channel('energy')->error('Exception while renting energy', [
                'wallet_id' => $wallet?->id,
                'energy_amount' => $energyAmount,
                'service' => $activeService->getCode(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Energy rental failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Returns the active provider balance
     *
     * @return array|null Balance, or null if there is no active provider
     */
    public function getBalance(): ?array
    {
        $activeService = $this->getActiveService();

        if (!$activeService) {
            return null;
        }

        try {
            return $activeService->getBalance();
        } catch (\Throwable $e) {
            Log::channel('energy')->error('Error getting energy service balance', [
                'service' => $activeService->getCode(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Returns the energy cost for addresses
     *
     * @param array $addresses Addresses
     * @return array Cost per address
     */
    public function getCost(array $addresses): array
    {
        $activeService = $this->getActiveService();

        if (!$activeService) {
            Log::channel('energy')->warning('No active energy service found for cost calculation');
            return [];
        }

        try {
            return $activeService->getCost($addresses);
        } catch (\Throwable $e) {
            Log::channel('energy')->error('Error calculating energy cost', [
                'service' => $activeService->getCode(),
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Manages Smart Energy for an address via a specific provider by its DB ID.
     * Used on stop/delete to call the same provider that handled start.
     * Falls back to the current active provider if not found or unavailable.
     *
     * @param string $target TRC-20 address
     * @param string $action start | stop | delete
     * @param int|null $serviceId ID from energy_services (energy_wallets.energy_service_id)
     * @return array
     */
    public function smartEnergy(string $target, string $action, ?int $serviceId = null): array
    {
        $activeService = $serviceId
            ? ($this->getServiceById($serviceId) ?? $this->getActiveService())
            : $this->getActiveService();

        if (!$activeService) {
            Log::channel('energy')->warning('smartEnergy: no active energy service', [
                'target' => $target,
                'action' => $action,
            ]);

            return ['success' => false, 'message' => 'No active energy provider', 'service_id' => null];
        }

        $serviceCode = $activeService->getCode();
        $serviceModel = EnergyService::where('code', $serviceCode)->first();
        $resolvedId = $serviceModel?->id;

        Log::channel('energy')->info('smartEnergy dispatch', [
            'service' => $serviceCode,
            'service_id' => $resolvedId,
            'target' => $target,
            'action' => $action,
            'requested_service_id' => $serviceId,
        ]);

        try {
            $result = $activeService->smartEnergy($target, $action);
            $result['service_id'] = $resolvedId;

            $level = $result['success'] ? 'info' : 'warning';
            Log::channel('energy')->$level('smartEnergy result', [
                'service' => $serviceCode,
                'target' => $target,
                'action' => $action,
                'result' => $result,
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::channel('energy')->error('smartEnergy exception', [
                'service' => $serviceCode,
                'target' => $target,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage(), 'service_id' => $resolvedId];
        }
    }

    /**
     * Finds the provider implementation by its energy_services ID.
     * Returns null if not found in the DB or there is no matching implementation.
     */
    private function getServiceById(int $id): ?EnergyServiceInterface
    {
        $code = EnergyService::find($id)?->code;
        if (!$code) {
            return null;
        }

        return $this->services->first(fn(EnergyServiceInterface $s) => $s->getCode() === $code);
    }

    /**
     * Returns the active energy provider with the highest priority
     */
    private function getActiveService(): ?EnergyServiceInterface
    {
        $activeServiceCodes = EnergyService::where('is_enabled', true)
            ->orderBy('priority')
            ->pluck('code')
            ->toArray();

        $availableServices = $this->services->filter(function (EnergyServiceInterface $service) use ($activeServiceCodes) {
            return in_array($service->getCode(), $activeServiceCodes, true);
        });

        return $availableServices->first();
    }

    /**
     * Returns all active energy providers
     */
    private function getActiveServices(): Collection
    {
        $activeServiceCodes = EnergyService::where('is_enabled', true)
            ->orderBy('priority')
            ->pluck('code')
            ->toArray();

        return $this->services->filter(function (EnergyServiceInterface $service) use ($activeServiceCodes) {
            return in_array($service->getCode(), $activeServiceCodes, true);
        });
    }

    /**
     * Returns a provider by code
     */
    public function getService(string $code): ?EnergyServiceInterface
    {
        return $this->services->first(function (EnergyServiceInterface $service) use ($code) {
            return $service->getCode() === $code;
        });
    }

    /**
     * Returns all available providers
     */
    public function getAllServices(): Collection
    {
        return $this->services;
    }
}

