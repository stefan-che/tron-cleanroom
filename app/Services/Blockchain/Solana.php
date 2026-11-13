<?php

namespace App\Services\Blockchain;

use App\Services\Blockchain\Services\AbstractBlockchainService;
use Illuminate\Support\Facades\Http;

/**
 * Solana Blockchain Service
 *
 * @link https://solana.com/ru/docs/rpc/http/getsignaturesforaddress
 */
class Solana extends AbstractBlockchainService
{
    private const SOLANA_RPC_URL = 'https://api.mainnet-beta.solana.com';

    /**
     * Get transaction count for a Solana address
     */
    public function getTransactionCount(string $address): int
    {
        $data = $this->makeRpcRequest('getSignaturesForAddress', [
            $address,
            ['limit' => 1000]
        ]);

        if ($data && isset($data['result']) && is_array($data['result'])) {
            return count($data['result']);
        }

        return 0;
    }

    /**
     * Get the latest transaction hash for a Solana address
     */
    public function getLastTransactionHash(string $address): ?string
    {
        $data = $this->makeRpcRequest('getSignaturesForAddress', [
            $address,
            ['limit' => 1]
        ]);

        if ($data && isset($data['result']) && !empty($data['result'])) {
            return $data['result'][0]['signature'] ?? null;
        }

        return null;
    }

    /**
     * Get account info
     */
    public function getAccountInfo(string $address): array
    {
        $data = $this->makeRpcRequest('getAccountInfo', [
            $address,
            ['encoding' => 'jsonParsed']
        ]);

        if ($data && isset($data['result']['value'])) {
            return [
                'address' => $address,
                'lamports' => $data['result']['value']['lamports'] ?? 0,
                'owner' => $data['result']['value']['owner'] ?? null,
                'executable' => $data['result']['value']['executable'] ?? false,
                'rent_epoch' => $data['result']['value']['rentEpoch'] ?? null,
            ];
        }

        return [];
    }

    /**
     * Get account balance
     */
    public function getBalance(string $address): float
    {
        $data = $this->makeRpcRequest('getBalance', [$address]);

        if ($data && isset($data['result']['value'])) {
            return $data['result']['value'] / 1_000_000_000; // Convert from lamports to SOL
        }

        return 0.0;
    }

    /**
     * Helper for Solana RPC requests
     */
    private function makeRpcRequest(string $method, array $params): ?array
    {
        try {
            $response = Http::timeout($this->timeout)->post(self::SOLANA_RPC_URL, [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => $method,
                'params' => $params
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            $this->logError($method, 'Solana RPC request failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

        } catch (\Exception $e) {
            $this->logError($method, $e->getMessage(), ['params' => $params]);
        }

        return null;
    }
}
