<?php

namespace App\Http\Controllers;

use App\Repositories\WalletRepository;
use App\Repositories\WalletWebhookRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WalletWebhookController extends Controller
{
    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request)
    {
        $rawBody = $request->getContent();
        $ipAddress = $request->ip();

        // Verify the signature before any parsing or DB writes. No secret or signature means rejection.
        if (!$this->isValidSignature($rawBody, (string)$request->header('X-Signature', ''))) {
            Log::channel('wallet_webhook')->warning('WalletWebhook: invalid or missing signature', [
                'ip_address' => $ipAddress,
                'has_signature' => $request->hasHeader('X-Signature'),
                'secret_configured' => $this->getSecret() !== '',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // The Go wallet-listener sends a form-encoded POST, so all() works correctly.
        $data = $request->all();

        // If form data is empty, try to parse the body as JSON
        if (empty($data) && !empty($rawBody)) {
            try {
                $data = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR) ?? [];
            } catch (\JsonException) {
                // the body is neither form data nor JSON; continue with an empty $data
            }
        }

        try {
            Log::channel('wallet_webhook')->info('Webhook received from wallet listener', [
                'ip_address' => $ipAddress,
                'timestamp' => now()->toDateTimeString(),
                'data' => $data,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
            ]);
        } catch (\Throwable $e) {
            // The wallet_webhook channel is unavailable; use the default logger
            Log::error('WalletWebhook: logging channel error', [
                'error' => $e->getMessage(),
                'ip' => $ipAddress,
            ]);
        }

        try {
            $address = $data['address'] ?? null;

            if ($address && $wallet = (new WalletRepository())->getByAddress($address)) {
                app(WalletWebhookRepository::class)->createFromWebhookData($wallet, $data, $ipAddress);
                app(WalletRepository::class)->syncTransactionWebHook($wallet, $data);
            }
        } catch (\Throwable $e) {
            Log::error('WalletWebhook: processing error', [
                'error' => $e->getMessage(),
                'data' => $data,
                'ip' => $ipAddress,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing error',
                'received_at' => now()->toDateTimeString(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Webhook received',
            'received_at' => now()->toDateTimeString(),
        ], 200);
    }

    /**
     * Listener signature: X-Signature: sha256=<hex hmac_sha256(raw body, secret)>.
     */
    private function isValidSignature(string $rawBody, string $signature): bool
    {
        $secret = $this->getSecret();

        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals('sha256=' . hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    private function getSecret(): string
    {
        return (string)config('services.wallet_listener.hmac_secret', '');
    }
}
