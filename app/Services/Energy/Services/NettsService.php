<?php

namespace App\Services\Energy\Services;

use App\Models\Energy\EnergyService;
use App\Services\Energy\Contracts\EnergyServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Netts.io energy provider
 * Docs: https://doc.netts.io/api/v2/endpoints/order1h.html
 */
class NettsService implements EnergyServiceInterface
{
    private const string API_URL = 'https://netts.io/apiv2';

    public function getCode(): string
    {
        return EnergyService::NETTS_CODE;
    }

    public function getName(): string
    {
        return 'Netts.io';
    }

    /**
     * Rents energy via Netts.io
     *
     * @param int $energyAmount Energy amount
     * @param string $receiverAddress Receiver address
     * @param string $duration Duration (1h, 1d)
     * @return array
     */
    public function rentEnergy(int $energyAmount, string $receiverAddress, string $duration = '1h'): array
    {

        try {
            Log::channel('energy')->info('Netts.io: Renting energy', [
                'energy_amount' => $energyAmount,
                'receiveAddress' => $receiverAddress,
                'duration' => $duration,
            ]);

            // Netts.io uses different endpoints per duration
            $endpoint = $this->getEndpoint($duration);

            $response = Http::timeout(60)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-API-KEY' => config('services.netts.api_key'),
                ])
                ->post(self::API_URL . $endpoint, [
                    'receiveAddress' => $receiverAddress,
                    'amount' => $energyAmount,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::channel('energy')->info('Netts.io: Energy rented successfully', [
                    'result' => $data,
                ]);

                return [
                    'success' => true,
                    'message' => 'Energy rented successfully via Netts.io',
                    'data' => [
                        'order_id' => $data['order_id'] ?? $data['txid'] ?? null,
                        'energy_amount' => $energyAmount,
                        'receiveAddress' => $receiverAddress,
                        'price_in_sun' => $data['price_in_sun'] ?? null,
                        'total_price' => $data['total_price'] ?? null,
                    ],
                ];
            }

            Log::channel('energy')->error('Netts.io: Failed to rent energy', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return [
                'success' => false,
                'message' => $response->json()['error'] ?? $response->json()['message'] ?? 'Energy rental failed',
                'data' => $response->json(),
            ];
        } catch (\Throwable $e) {
            Log::channel('energy')->error('Netts.io: Exception while renting energy', [
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
     * Returns the Netts.io balance
     *
     * @return array
     */
    public function getBalance(): array
    {
        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-API-KEY' => config('services.netts.api_key'),
                ])
                ->get(self::API_URL . '/userinfo');

            if ($response->successful()) {
                $data = $response->json();

                Log::channel('energy')->info('Netts.io: Balance retrieved', [
                    'result' => $data,
                ]);

                return [
                    'balance' => $data['stats']['balance'] ?? 0,
                ];
            }

            Log::channel('energy')->error('Netts.io: Error getting balance', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return [
                'balance' => 0,
                'error' => 'Failed to fetch balance',
            ];
        } catch (\Throwable $e) {
            Log::channel('energy')->error('Netts.io: Exception getting balance', [
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
                    'X-API-KEY' => config('services.netts.api_key'),
                ])
                ->get(self::API_URL . '/price');

            if ($response->successful()) {
                $data = $response->json();

                Log::channel('energy')->info('Netts.io: Price retrieved', [
                    'result' => $data,
                ]);

                // Return the price per energy unit for all addresses
                $result = [];
                $priceInSun = $data['price_in_sun'] ?? 0;

                foreach ($addresses as $address) {
                    $result[$address] = [
                        'price_in_sun' => $priceInSun,
                        'price_in_trx' => $priceInSun / 1000000,
                    ];
                }

                return $result;
            }

            return [];
        } catch (\Throwable $e) {
            Log::channel('energy')->error('Netts.io: Error calculating cost', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Netts.io returns the cost in total_price (SUN) -> converted to TRX.
     */
    public function getCostTrxFromRentResult(array $result): float
    {
        $priceInSun = (float) ($result['data']['total_price'] ?? 0);

        return $priceInSun > 0 ? $priceInSun / 1_000_000 : 0.0;
    }

    /**
     * Smart Energy is not supported by Netts.io - interface stub.
     */
    public function smartEnergy(string $target, string $action): array
    {
        Log::channel('energy')->info('Netts.io: smartEnergy not supported', [
            'target' => $target,
            'action' => $action,
        ]);

        return [
            'success' => false,
            'message' => 'Smart Energy is not supported by Netts.io',
        ];
    }

    /**
     * Returns the endpoint for the given duration
     */
    private function getEndpoint(string $duration): string
    {
        return match ($duration) {
            '5m', '1h' => '/order1h',
            '1d' => '/order1d',
            default => '/order1h',
        };
    }
}
