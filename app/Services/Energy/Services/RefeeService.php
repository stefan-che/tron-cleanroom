<?php

namespace App\Services\Energy\Services;

use App\Models\Energy\EnergyService;
use App\Services\Energy\Contracts\EnergyServiceInterface;
use App\Services\Refee\Balance;
use App\Services\Refee\BuyEnergy;
use App\Services\Refee\Cost;
use Illuminate\Support\Facades\Log;

/**
 * @site https://refee.bot/ru/
 * @docs https://documenter.getpostman.com/view/45996988/2sB2xBBp4X
 */
class RefeeService implements EnergyServiceInterface
{
    public function getCode(): string
    {
        return EnergyService::REFEE_CODE;
    }

    public function getName(): string
    {
        return 'ReFee';
    }

    /**
     * Rents energy via ReFee
     *
     * @param int $energyAmount Energy amount
     * @param string $receiverAddress Receiver address
     * @param string $duration Duration (5m, 1h, 1d, 3d, 15d)
     * @return array
     */
    public function rentEnergy(int $energyAmount, string $receiverAddress, string $duration = '1h'): array
    {
        try {
            Log::channel('energy')->info('ReFee: Renting energy', [
                'energy_amount' => $energyAmount,
                'receiver_address' => $receiverAddress,
                'duration' => $duration,
            ]);

            $buyEnergy = new BuyEnergy();
            $result = $buyEnergy->buy($duration, $energyAmount, $receiverAddress);

            if ($buyEnergy->isSuccess()) {
                Log::channel('energy')->info('ReFee: Energy rented successfully', [
                    'result' => $result,
                ]);

                return [
                    'success' => true,
                    'message' => 'Energy rented successfully via ReFee',
                    'data' => [
                        'order_id' => $result['order_id'] ?? null,
                        'energy_amount' => $result['volume'] ?? $energyAmount,
                        'receiver_address' => $result['target'] ?? $receiverAddress,
                        'price' => $result['price'] ?? null,
                        'summa' => $result['summa'] ?? null,
                        'balance' => $result['balance'] ?? null,
                        'days' => $result['days'] ?? null,
                        'status' => $result['status'] ?? null,
                    ],
                ];
            }

            Log::channel('energy')->error('ReFee: Failed to rent energy', [
                'error' => $buyEnergy->getError(),
                'result' => $result,
            ]);

            return [
                'success' => false,
                'message' => $buyEnergy->getError(),
                'data' => $result,
            ];
        } catch (\Throwable $e) {
            Log::channel('energy')->error('ReFee: Exception while renting energy', [
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
     * Returns the ReFee balance
     *
     * @return array
     */
    public function getBalance(): array
    {
        try {
            $balance = new Balance();
            $result = $balance->balance();

            Log::channel('energy')->info('ReFee: Balance retrieved', [
                'result' => $result,
            ]);

            return [
                'balance' => $result['balance'] ?? 0,
                'username' => $result['username'] ?? null,
                'full_name' => $result['full_name'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::channel('energy')->error('ReFee: Error getting balance', [
                'error' => $e->getMessage(),
            ]);

            return [
                'balance' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Refee returns the cost in the summa field (TRX).
     */
    public function getCostTrxFromRentResult(array $result): float
    {
        return (float) ($result['data']['summa'] ?? 0);
    }

    // SMART ENERGY

    /**
     * Manages Smart Energy for an address.
     * GET /smart_energy
     *
     * @param string $target  TRC-20 wallet address
     * @param string $action  start | stop | delete
     * @return array
     */
    public function smartEnergy(string $target, string $action): array
    {
        try {
            Log::channel('energy')->info('ReFee: Smart Energy action', [
                'target' => $target,
                'action' => $action,
            ]);

            $refee = new \App\Services\Refee\ReFee('/smart_energy', [
                'target' => $target,
                'action' => $action,
            ]);
            $result = $refee->execute();

            if (isset($result['error'])) {
                Log::channel('energy')->error('ReFee: Smart Energy action failed', [
                    'target' => $target,
                    'action' => $action,
                    'error'  => $result,
                ]);

                return ['success' => false, 'message' => $result['error'], 'data' => $result];
            }

            Log::channel('energy')->info('ReFee: Smart Energy action success', [
                'target' => $target,
                'action' => $action,
                'result' => $result,
            ]);

            return ['success' => true, 'data' => $result];
        } catch (\Throwable $e) {
            Log::channel('energy')->error('ReFee: Smart Energy exception', [
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Addresses connected to Smart Energy.
     * GET /smart_list
     *
     * @return array
     */
    public function getSmartList(): array
    {
        try {
            $refee = new \App\Services\Refee\ReFee('/smart_list');
            $result = $refee->execute();

            if (isset($result['error'])) {
                Log::channel('energy')->error('ReFee: Smart List failed', ['error' => $result]);
                return ['success' => false, 'message' => $result['error'], 'data' => $result];
            }

            return ['success' => true, 'data' => $result];
        } catch (\Throwable $e) {
            Log::channel('energy')->error('ReFee: Smart List exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Smart Energy action log (Always Charged).
     * GET /smart_logs
     *
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getSmartLogs(int $page = 1, int $perPage = 20): array
    {
        try {
            $refee = new \App\Services\Refee\ReFee('/smart_logs', [
                'page'     => $page,
                'per_page' => $perPage,
            ]);
            $result = $refee->execute();

            if (isset($result['error'])) {
                Log::channel('energy')->error('ReFee: Smart Logs failed', ['error' => $result]);
                return ['success' => false, 'message' => $result['error'], 'data' => $result];
            }

            return ['success' => true, 'data' => $result];
        } catch (\Throwable $e) {
            Log::channel('energy')->error('ReFee: Smart Logs exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Smart Energy orders (Always Charged).
     * GET /smart_orders
     *
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getSmartOrders(int $page = 1, int $perPage = 20): array
    {
        try {
            $refee = new \App\Services\Refee\ReFee('/smart_orders', [
                'page'     => $page,
                'per_page' => $perPage,
            ]);
            $result = $refee->execute();

            if (isset($result['error'])) {
                Log::channel('energy')->error('ReFee: Smart Orders failed', ['error' => $result]);
                return ['success' => false, 'message' => $result['error'], 'data' => $result];
            }

            return ['success' => true, 'data' => $result];
        } catch (\Throwable $e) {
            Log::channel('energy')->error('ReFee: Smart Orders exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Returns the required energy for addresses
     *
     * @param array $addresses
     * @return array
     */
    public function getCost(array $addresses): array
    {
        try {
            $cost = new Cost();
            $result = $cost->result($addresses);

            Log::channel('energy')->info('ReFee: Cost calculated', [
                'addresses' => $addresses,
                'result' => $result,
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::channel('energy')->error('ReFee: Error calculating cost', [
                'error' => $e->getMessage(),
                'addresses' => $addresses,
            ]);

            return [];
        }
    }
}
