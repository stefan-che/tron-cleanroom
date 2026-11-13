<?php

namespace App\Jobs\Wallet;

use App\Models\Wallet\Wallet;
use App\Repositories\WalletRepository;
use App\Services\Wallet\WalletListenerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWalletToListenerService implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    /**
     * @param int $walletId
     * @param int $ttlHours
     */
    public function __construct(
        readonly public int $walletId,
        readonly public int $ttlHours = 48,
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
            Log::channel('wallet_webhook')->warning('SendWalletToListenerService: Wallet not found', [
                'wallet_id' => $this->walletId,
            ]);
            return;
        }

        $service = new WalletListenerService();

        if (!$service->isConfigured()) {
            Log::channel('wallet_webhook')->warning('SendWalletToListenerService: External service URL is not configured', [
                'wallet_id' => $wallet->id,
                'address' => $wallet->address,
            ]);
            return;
        }

        $result = $service->sendWallet($wallet, $this->ttlHours);

        if (!($result['success'] ?? false)) {
            Log::channel('wallet_webhook')->error('SendWalletToListenerService: Failed to send the wallet to the external service', [
                'wallet_id' => $wallet->id,
                'address' => $wallet->address,
                'result' => $result,
                'attempt' => $this->attempts(),
            ]);

            // If this is not the last attempt, retry in a minute
            if ($this->attempts() < $this->tries) {
                $this->release(60);
            }
        }
    }

    /**
     * Handle a job failure.
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::channel('wallet_webhook')->error('SendWalletToListenerService: Job failed', [
            'wallet_id' => $this->walletId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'attempt' => $this->attempts(),
        ]);
    }
}
