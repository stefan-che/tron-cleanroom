<?php

namespace App\Jobs\Wallet;

use App\Models\Wallet\Wallet;
use App\Repositories\WalletRepository;
use App\Services\RecursiveAml\RecursiveAmlService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Puts the wallet under monitoring in the recursive AML service.
 *
 * Queue-only job: the API client doesn't need the response, and an external
 * service failure must not break wallet generation.
 */
class SendWalletToRecursiveAml implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    /**
     * @param int $walletId
     */
    public function __construct(
        readonly public int $walletId,
    )
    {
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle()
    {
        $walletRepository = new WalletRepository();

        /**
         * @var Wallet $wallet
         */
        $wallet = $walletRepository->getById($this->walletId);

        if (!$wallet) {
            Log::channel(RecursiveAmlService::LOG_CHANNEL)->warning('SendWalletToRecursiveAml: Wallet not found', [
                'wallet_id' => $this->walletId,
            ]);
            return;
        }

        $service = new RecursiveAmlService();

        if (!$service->isEnabled()) {
            return;
        }

        if (!$service->isConfigured()) {
            Log::channel(RecursiveAmlService::LOG_CHANNEL)->warning('SendWalletToRecursiveAml: External service URL is not configured', [
                'wallet_id' => $wallet->id,
                'address' => $wallet->address,
            ]);
            return;
        }

        if (!$service->isSupported($wallet)) {
            Log::channel(RecursiveAmlService::LOG_CHANNEL)->info('SendWalletToRecursiveAml: Wallet network not supported, skipping', [
                'wallet_id' => $wallet->id,
                'address' => $wallet->address,
                'blockchain_type' => $wallet->blockchain_type,
            ]);
            return;
        }

        $result = $service->createInvestigation($wallet);

        if ($result['success'] ?? false) {
            return;
        }

        Log::channel(RecursiveAmlService::LOG_CHANNEL)->error('SendWalletToRecursiveAml: Failed to put the wallet under monitoring', [
            'wallet_id' => $wallet->id,
            'address' => $wallet->address,
            'result' => $result,
            'attempt' => $this->attempts(),
        ]);

        // Retrying a request error (bad address, wallet stop, missing key) is
        // pointless — only network failures and 5xx are retried.
        if (!($result['retryable'] ?? false)) {
            return;
        }

        // If this is not the last attempt, retry in a minute
        if ($this->attempts() < $this->tries) {
            $this->release(60);
        }
    }

    /**
     * Handle a job failure.
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::channel(RecursiveAmlService::LOG_CHANNEL)->error('SendWalletToRecursiveAml: Job failed', [
            'wallet_id' => $this->walletId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'attempt' => $this->attempts(),
        ]);
    }
}
