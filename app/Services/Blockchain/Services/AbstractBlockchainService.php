<?php

namespace App\Services\Blockchain\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class AbstractBlockchainService implements BlockchainServiceInterface
{
    protected int $timeout = 30;

    /**
     * Check whether there are new transactions
     */
    public function hasNewTransactions(string $address, ?string $lastKnownHash): bool
    {
        $currentHash = $this->getLastTransactionHash($address);

        if (!$currentHash) {
            return false;
        }

        if (!$lastKnownHash) {
            return true; // No previous hash - assume there are new ones
        }

        return $currentHash !== $lastKnownHash;
    }

    /**
     * Get account balance (0 by default)
     */
    public function getBalance(string $address): float
    {
        return 0.0;
    }

    protected function logError(string $method, string $message, array $context = []): void
    {
        Log::channel('aml')->info("Blockchain Service Error - {$method}", [
            'message' => $message,
            'context' => $context,
        ]);
    }

    /**
     * HTTP request with error handling
     */
    protected function makeHttpRequest(string $url, array $params = [], string $method = 'GET', array $headers = []): ?array
    {
        try {

            $request = Http::timeout($this->timeout);

            if (!empty($headers)) {
                $request = $request->withHeaders($headers);
            }

            if (strtoupper($method) === 'GET') {
                $response = $request->get($url, $params);
            } else {
                $response = $request->post($url, $params);
            }

            if ($response->successful()) {
                return $response->json();
            }

            $this->logError(__FUNCTION__, "HTTP request failed", [
                'url' => $url,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

        } catch (\Exception $e) {
            $this->logError(__FUNCTION__, $e->getMessage(), [
                'url' => $url,
                'params' => $params
            ]);
        }

        return null;
    }
}
