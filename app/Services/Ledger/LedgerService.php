<?php

namespace App\Services\Ledger;

use App\Models\Recipient\LedgerAccountWallet;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

// External ledger client (account bookkeeping). Optional: without url/token all calls return 503.
// API contract: docs/LEDGER_INTEGRATION.md.
class LedgerService
{
    private string $baseUrl;
    private string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.ledger.url', ''), '/');
        $this->token = (string) config('services.ledger.token', '');
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->token !== '';
    }

    public function health(): array
    {
        return $this->send(fn() => Http::get("{$this->baseUrl}/health"));
    }

    public function getGroups(int $page = 1, int $perPage = 20, ?int $tgId = null): array
    {
        $params = ['page' => $page, 'per_page' => $perPage];

        if ($tgId !== null) {
            $params['tg_id'] = $tgId;
        }

        return $this->send(fn() => $this->client()->get("{$this->baseUrl}/api/groups", $params));
    }

    public function getAccounts(int $page = 1, int $perPage = 20, ?int $userId = null, ?string $groupId = null): array
    {
        $params = ['page' => $page, 'per_page' => $perPage];

        if ($userId !== null) {
            $params['user_id'] = $userId;
        }

        if ($groupId !== null) {
            $params['group_id'] = $groupId;
        }

        return $this->send(fn() => $this->client()->get("{$this->baseUrl}/api/accounts", $params));
    }

    public function updateAccountWallet(string $accountId, ?string $walletAddress, ?string $network = ''): array
    {
        return $this->send(fn() => $this->client()
            ->post("{$this->baseUrl}/api/accounts/{$accountId}/wallets", [
                'wallet_address' => $walletAddress,
                'network' => $network ?: LedgerAccountWallet::NETWORK_TRON,
            ]));
    }

    public function deleteAccountWallet(string $accountId, string $walletId): array
    {
        return $this->send(fn() => $this->client()
            ->delete("{$this->baseUrl}/api/accounts/{$accountId}/wallets/{$walletId}"));
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders(['X-API-KEY' => $this->token]);
    }

    /**
     * @param callable(): Response $request
     */
    private function send(callable $request): array
    {
        if (!$this->isConfigured()) {
            return ['status' => 503, 'ok' => false, 'body' => ['error' => 'Ledger integration is not configured']];
        }

        try {
            return $this->parse($request());
        } catch (ConnectionException $e) {
            // status 0 - the caller responds with 502
            return ['status' => 0, 'ok' => false, 'body' => ['error' => $e->getMessage()]];
        }
    }

    private function parse(Response $response): array
    {
        return [
            'status' => $response->status(),
            'ok' => $response->successful(),
            'body' => $response->json() ?? $response->body(),
        ];
    }
}
