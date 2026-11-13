<?php

namespace App\Services\RecursiveAml;

use App\Models\Recipient\Wallet as RecipientWallet;
use App\Models\Wallet\Wallet;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for the external recursive AML monitoring service (optional, RECURSIVE_AML_*).
 *
 * Its unit of monitoring is an investigation: a root address plus traversal settings.
 * There is no separate "add address" endpoint, so putting a wallet under monitoring =
 * creating an investigation for its address (POST /api/investigations).
 *
 * The service is TRON-only: it does not monitor addresses on other networks.
 */
class RecursiveAmlService
{
    public const string LOG_CHANNEL = 'recursive_aml';

    /**
     * Networks the external monitoring service can monitor.
     */
    private const array SUPPORTED_BLOCKCHAIN_TYPES = [
        RecipientWallet::BLOCKCHAIN_TYPE_TRC_20,
    ];

    private string $apiUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->apiUrl = rtrim((string)config('services.recursive_aml.url', ''), '/');
        $this->apiKey = (string)config('services.recursive_aml.api_key', '');
    }

    /**
     * Create an investigation for a wallet - put its root address under monitoring.
     *
     * @param Wallet $wallet
     * @return array{success: bool, retryable: bool, status_code?: int, investigation_id?: int|null, response?: mixed, error?: string}
     */
    public function createInvestigation(Wallet $wallet): array
    {
        $payload = $this->buildPayload($wallet);

        try {
            $response = Http::timeout(30)
                ->connectTimeout(10)
                ->withHeaders(['X-API-Key' => $this->apiKey])
                ->acceptJson()
                ->post($this->apiUrl . '/api/investigations', $payload);
        } catch (\Throwable $e) {
            Log::channel(self::LOG_CHANNEL)->error('RecursiveAmlService: Exception while creating investigation', [
                'wallet_id' => $wallet->id,
                'address' => $wallet->address,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Network error/timeout - worth retrying.
            return [
                'success' => false,
                'retryable' => true,
                'error' => $e->getMessage(),
            ];
        }

        return $this->handleResponse($wallet, $payload, $response);
    }

    /**
     * Response handling: 201 - created; 400 "already exists" - the wallet is already monitored;
     * other 4xx - request or configuration error, retrying is pointless.
     *
     * @param Wallet $wallet
     * @param array $payload
     * @param Response $response
     * @return array{success: bool, retryable: bool, status_code: int, investigation_id?: int|null, response: mixed}
     */
    private function handleResponse(Wallet $wallet, array $payload, Response $response): array
    {
        $body = $response->json();
        $status = $response->status();

        $result = [
            'success' => $response->successful(),
            'retryable' => $response->serverError(),
            'status_code' => $status,
            'response' => $body,
        ];

        if ($response->successful()) {
            $result['investigation_id'] = $body['id'] ?? null;

            Log::channel(self::LOG_CHANNEL)->info('RecursiveAmlService: Wallet put under monitoring', [
                'wallet_id' => $wallet->id,
                'address' => $wallet->address,
                'investigation_id' => $result['investigation_id'],
                'url' => $this->apiUrl,
                'payload' => $payload,
                'result' => $result,
            ]);

            return $result;
        }

        $error = (string)($body['error'] ?? $response->body());

        // "investigation for this address already exists (id N)" is not an integration error:
        // the address is already monitored, no need to retry.
        if ($status === 400 && $existingId = $this->parseExistingInvestigationId($error)) {
            Log::channel(self::LOG_CHANNEL)->info('RecursiveAmlService: Wallet already under monitoring', [
                'wallet_id' => $wallet->id,
                'address' => $wallet->address,
                'investigation_id' => $existingId,
                'error' => $error,
            ]);

            return [
                'success' => true,
                'retryable' => false,
                'status_code' => $status,
                'investigation_id' => $existingId,
                'response' => $body,
            ];
        }

        Log::channel(self::LOG_CHANNEL)->error('RecursiveAmlService: Failed to create investigation', [
            'wallet_id' => $wallet->id,
            'address' => $wallet->address,
            'status_code' => $status,
            'error' => $error,
            'payload' => $payload,
            'retryable' => $result['retryable'],
        ]);

        return $result;
    }

    /**
     * Request body. Only the address is required; traversal parameters not set
     * in config are omitted - the service applies its own defaults.
     *
     * @param Wallet $wallet
     * @return array
     */
    private function buildPayload(Wallet $wallet): array
    {
        $payload = [
            'address' => $wallet->address,
            'title' => $this->buildTitle($wallet),
        ];

        return $payload;
    }

    /**
     * Investigation title in the list: the operator uses it to find our request.
     *
     * @param Wallet $wallet
     * @return string
     */
    private function buildTitle(Wallet $wallet): string
    {
        $parts = array_filter([
            $wallet->external_id ? 'Request ' . $wallet->external_id : null,
            'wallet #' . $wallet->id,
        ]);

        return implode(', ', $parts);
    }

    /**
     * @param string $error
     * @return int|null
     */
    private function parseExistingInvestigationId(string $error): ?int
    {
        // Matches the external service's Russian error text verbatim ("already exists").
        if (!str_contains($error, 'уже есть')) {
            return null;
        }

        return preg_match('/id\s*(\d+)/u', $error, $matches) ? (int)$matches[1] : null;
    }

    /**
     * Whether we monitor wallets on this network.
     *
     * @param Wallet $wallet
     * @return bool
     */
    public function isSupported(Wallet $wallet): bool
    {
        return in_array($wallet->blockchain_type, self::SUPPORTED_BLOCKCHAIN_TYPES, true);
    }

    /**
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiUrl);
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (bool)config('services.recursive_aml.enabled', false);
    }

}
