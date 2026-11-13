<?php

namespace App\Services\Wallet;

use App\Models\Recipient\Wallet as RecipientWallet;
use App\Models\Wallet\Wallet;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WalletListenerService
{
    private const array NETWORK_MAP = [
        RecipientWallet::BLOCKCHAIN_TYPE_TRC_20 => 'tron',
        RecipientWallet::BLOCKCHAIN_TYPE_BTC => 'bitcoin',
        RecipientWallet::BLOCKCHAIN_TYPE_ETH => 'ethereum',
    ];

    private string $apiUrl;
    private string $webhookSecret;
    private string $hmacSecret;

    public function __construct()
    {
        $this->apiUrl = rtrim(config('services.wallet_listener.url', ''), '/');
        $this->webhookSecret = config('services.wallet_listener.webhook_secret', '');
        $this->hmacSecret = (string)config('services.wallet_listener.hmac_secret', '');
    }

    /**
     * @param Wallet $wallet
     * @param int $ttlHours
     * @return array
     */
    public function sendWallet(
        Wallet $wallet,
        int    $ttlHours = 168,
    ): array
    {
        $network = self::NETWORK_MAP[$wallet->blockchain_type] ?? null;

        $payload = [
            'address' => $wallet->address,
            'network' => $network,
            'ttl_hours' => $ttlHours,
            'webhooks' => [
                [
                    'url' => route('webhook.wallet-listener'),
                    // Listener signs the webhook: X-Signature: sha256=<hmac_sha256(body, secret)>
                    'secret' => $this->hmacSecret,
                    'timeout_sec' => 10,
                    'max_retries' => 3,
                ],
            ],
            'meta' => [
                'label' => $wallet->external_id,
                'id' => $wallet->id,
            ],
        ];

        try {

            $response = Http::timeout(30)
                ->retry(3, 1000)
                ->withHeaders(['X-API-Key' => $this->webhookSecret])
                ->post($this->apiUrl . '/api/watch', $payload);

            $result = [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'response' => $response->json(),
            ];

            if ($response->successful()) {
                Log::channel('wallet_webhook')->info('WalletListenerService: wallet sent successfully', [
                    'wallet_id' => $wallet->id,
                    'address' => $wallet->address,
                    'url' => $this->apiUrl,
                    'result' => $result,
                    //Exclude webhooks: they contain the signing secret
                    'payload' => array_diff_key($payload, ['webhooks' => true]),
                ]);
            } else {
                Log::channel('wallet_webhook')->error('WalletListenerService: failed to send wallet', [
                    'wallet_id' => $wallet->id,
                    'address' => $wallet->address,
                    'result' => $result,
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::channel('wallet_webhook')->error('WalletListenerService: exception while sending wallet', [
                'wallet_id' => $wallet->id,
                'address' => $wallet->address,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiUrl);
    }
}
