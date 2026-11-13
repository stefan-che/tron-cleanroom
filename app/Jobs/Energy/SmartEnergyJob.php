<?php

namespace App\Jobs\Energy;

use App\Enums\Energy\EnergyWalletEvent;
use App\Helpers\Development;
use App\Helpers\TelegramHelper;
use App\Models\Energy\EnergyWallet;
use App\Models\Setting;
use App\Repositories\WalletTrxRepository;
use App\Services\Energy\EnergyServiceDispatcher;
use App\Services\Tron\TronService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmartEnergyJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, Queueable, InteractsWithQueue;

    public int $tries = 5;

    /** Timeout of a single attempt (seconds). */
    public int $timeout = 60;

    /** Delays between retries: 30s → 1m → 2m → 5m → 10m. */
    public function backoff(): array
    {
        return [30, 60, 120, 300, 600];
    }

    /**
     * @param int $walletId Wallet ID (int, since the model is soft-deleted on removal)
     * @param EnergyWalletEvent $event SMART_START | SMART_STOP
     * @param string $actor Who initiated it (for history)
     * @param string $note Context for history
     */
    public function __construct(
        public readonly int               $walletId,
        public readonly EnergyWalletEvent $event,
        public readonly string            $actor = 'system',
        public readonly string            $note = '',
    )
    {
    }

    public function handle(EnergyServiceDispatcher $dispatcher): void
    {
        if (Development::isLocal()) {
            return;
        }

        /**
         * @var EnergyWallet $wallet
         */
        $wallet = EnergyWallet::withTrashed()->find($this->walletId);

        if (!$wallet) {
            Log::channel('energy')->warning('SmartEnergyJob: wallet not found', [
                'wallet_id' => $this->walletId,
                'event' => $this->event->value,
            ]);
            return;
        }

        Log::channel('energy')->info('SmartEnergyJob handle start', [
            'wallet_id' => $wallet->id,
            'event' => $this->event->value,
            'actor' => $this->actor,
        ]);

        // Before start, check the TRX balance — the wallet must be activated
        if ($this->event === EnergyWalletEvent::SMART_START) {
            $this->ensureActivated($wallet);
        }

        $result = $dispatcher->smartEnergy(
            $wallet->address,
            $this->event->apiAction(),
            $wallet->energy_service_id,
        );

        if (!$result['success']) {
            Log::channel('energy')->warning('SmartEnergyJob: API returned failure, will retry', [
                'wallet_id' => $this->walletId,
                'event' => $this->event->value,
                'attempt' => $this->attempts(),
                'message' => $result['message'] ?? '?',
            ]);

            throw new \RuntimeException($result['message'] ?? 'SmartEnergy API error');
        }

        $updates = ['is_error' => false];

        if ($this->event === EnergyWalletEvent::SMART_START && isset($result['service_id'])) {
            $updates['energy_service_id'] = $result['service_id'];
        }

        $wallet->updateQuietly($updates);

        $wallet->addHistory($this->event, $this->actor, $this->note ?: null, $result);
    }

    public function failed(Throwable $e): void
    {
        $wallet = EnergyWallet::withTrashed()->find($this->walletId);

        if ($wallet) {
            $wallet->updateQuietly(['is_error' => true]);

            $wallet->addHistory(
                $this->event,
                $this->actor,
                "[failed after {$this->tries} attempts] " . $e->getMessage(),
            );

            $action = $this->event === EnergyWalletEvent::SMART_START ? 'enable' : 'disable';
            $label = $wallet->label ? " ({$wallet->label})" : '';
            $message = implode("\n", [
                "❌ <b>Energy auto-replenishment: error</b>",
                "",
                "Failed to <b>{$action}</b> the wallet after {$this->tries} attempts:",
                "<code>{$wallet->address}</code>{$label}",
                "",
                "Reason: " . $e->getMessage(),
            ]);
        } else {
            $message = implode("\n", [
                "❌ <b>Energy auto-replenishment: error</b>",
                "",
                "Failed to run <b>{$this->event->value}</b> for wallet_id={$this->walletId}",
                "Wallet not found in the database.",
                "",
                "Reason: " . $e->getMessage(),
            ]);
        }

        TelegramHelper::sendMessage($message);

        Log::channel('energy')->error('SmartEnergyJob: all retries exhausted', [
            'wallet_id' => $this->walletId,
            'event' => $this->event->value,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * Checks that the wallet has TRX.
     * If not enough, sends TRX for activation and waits 5 seconds.
     */
    private function ensureActivated(EnergyWallet $wallet): void
    {
        $requiredTrx = Setting::getCountTrxForActivateWallet();
        $currentTrx = app(TronService::class)->getBalanceTrx($wallet->address);

        if ($currentTrx >= 0.00001) {
            return; // Wallet is already activated
        }

        Log::channel('energy')->info('SmartEnergyJob: wallet needs TRX activation', [
            'wallet_id' => $this->walletId,
            'address' => $wallet->address,
            'current_trx' => $currentTrx,
            'required_trx' => $requiredTrx,
        ]);

        $trxWallet = app(WalletTrxRepository::class)->getRandomWallet();

        if (!$trxWallet) {
            throw new \RuntimeException(
                "No TRX wallets with sufficient balance to activate {$wallet->address}"
            );
        }

        $result = app(TronService::class)->sendTrxFromTrxWallet($trxWallet, $wallet->address, $requiredTrx);

        if (!$result['success']) {
            throw new \RuntimeException(
                'Failed to send TRX for activation: ' . ($result['error'] ?? $result['message'] ?? '?')
            );
        }

        $wallet->addHistory(
            EnergyWalletEvent::SMART_START,
            $this->actor,
            "Sent {$requiredTrx} TRX for activation (balance was: {$currentTrx} TRX)",
        );

        Log::channel('energy')->info('SmartEnergyJob: TRX sent for activation', [
            'wallet_id' => $this->walletId,
            'address' => $wallet->address,
            'amount_sent' => $requiredTrx,
            'from' => $trxWallet->address,
        ]);

        sleep(5);
    }

    public function uniqueId(): string
    {
        return "smart_energy_{$this->walletId}_{$this->event->value}";
    }
}
