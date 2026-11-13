<?php

namespace App\Services\Energy\Services;

use App\Models\Energy\EnergyService;
use App\Services\Energy\Contracts\EnergyServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tr.energy energy provider
 * Docs: https://documenter.getpostman.com/view/7317157/2s9YRGw8qy
 */
class TrEnergyService implements EnergyServiceInterface
{
    private const string API_URL = 'https://core.tr.energy/api';

    public function getCode(): string
    {
        return EnergyService::TR_ENERGY_CODE;
    }

    public function getName(): string
    {
        return 'Tr.energy';
    }

    /**
     * Rents energy via Tr.energy
     * @param int $energyAmount Energy amount
     * @param string $receiverAddress Receiver address
     * @param string $duration Duration (1h, 1d, 3d, 7d, 14d, 30d)
     * @return array
     */
    public function rentEnergy(int $energyAmount, string $receiverAddress, string $duration = '1h'): array
    {
        try {
            Log::channel('energy')->info('Tr.energy: Renting energy', [
                'energy_amount' => $energyAmount,
                'receiver_address' => $receiverAddress,
                'duration' => $duration,
            ]);

            // Convert duration to the API format (hours)
            $hours = $this->parseDuration($duration);

            $response = Http::timeout(60)
                ->withToken(config('services.tr_energy.api_key'))
                ->post(self::API_URL . '/consumers/bootstrap-order', [
                    'payment_period' => 15, //minutes
                    'address' => $receiverAddress,
                    'auto_renewal' => '0',
                    'resource_amount' => $energyAmount,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::channel('energy')->info('Tr.energy: Energy rented successfully', [
                    'result' => $data,
                ]);

                return [
                    'success' => true,
                    'message' => 'Energy rented successfully via Tr.energy',
                    'data' => [
                        'order_id' => $data['order_id'] ?? null,
                        'energy_amount' => $energyAmount,
                        'receiver_address' => $receiverAddress,
                        'price' => $data['price'] ?? null,
                        'period' => $hours,
                    ],
                ];
            }

            Log::channel('energy')->error('Tr.energy: Failed to rent energy', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return [
                'success' => false,
                'message' => $response->json()['message'] ?? 'Energy rental failed',
                'data' => $response->json(),
            ];
        } catch (\Throwable $e) {
            Log::channel('energy')->error('Tr.energy: Exception while renting energy', [
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
     * Returns the Tr.energy balance
     *
     * @return array
     */
    public function getBalance(): array
    {
        try {
            $response = Http::timeout(60)
                ->withToken(config('services.tr_energy.api_key'))
                ->get(self::API_URL . '/account');

            if ($response->successful()) {
                $data = $response->json();

                Log::channel('energy')->info('Tr.energy: Balance retrieved', [
                    'result' => $data,
                ]);

                return [
                    'balance' => $data['data']['balance'] ?? 0,
                ];
            }

            Log::channel('energy')->error('Tr.energy: Error getting balance', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return [
                'balance' => 0,
                'error' => 'Failed to fetch balance',
            ];
        } catch (\Throwable $e) {
            Log::channel('energy')->error('Tr.energy: Exception getting balance', [
                'error' => $e->getMessage(),
            ]);

            return [
                'balance' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Returns the energy rental price
     *
     * @param array $addresses
     * @return array
     */
    public function getCost(array $addresses): array
    {
        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->get(self::API_URL . '/v1/frontend/price', [
                    'api_key' => config('services.tr_energy.api_key'),
                ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::channel('energy')->info('Tr.energy: Price retrieved', [
                    'result' => $data,
                ]);

                // Return the price per energy unit for all addresses
                $result = [];
                $pricePerUnit = $data['price_per_energy'] ?? 0;

                foreach ($addresses as $address) {
                    $result[$address] = [
                        'price_per_unit' => $pricePerUnit,
                        'currency' => 'TRX',
                    ];
                }

                return $result;
            }

            return [];
        } catch (\Throwable $e) {
            Log::channel('energy')->error('Tr.energy: Error calculating cost', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Tr.energy returns the cost in the price field (TRX).
     */
    public function getCostTrxFromRentResult(array $result): float
    {
        return (float) ($result['data']['price'] ?? 0);
    }

    /**
     * Smart Energy is not supported by Tr.energy - interface stub.
     */
    public function smartEnergy(string $target, string $action): array
    {
        Log::channel('energy')->info('Tr.energy: smartEnergy not supported', [
            'target' => $target,
            'action' => $action,
        ]);

        return [
            'success' => false,
            'message' => 'Smart Energy is not supported by Tr.energy',
        ];
    }

    /**
     * Converts duration to hours
     */
    private function parseDuration(string $duration): int
    {
        return match ($duration) {
            '5m' => 1, // 1 hour minimum
            '1h' => 1,
            '1d' => 24,
            '3d' => 72,
            '7d' => 168,
            '14d' => 336,
            '15d' => 360,
            '30d' => 720,
            default => 1,
        };
    }
}
