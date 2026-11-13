<?php

namespace App\Jobs\Wallet;

use App\Helpers\ArrayHelper;
use App\Helpers\TelegramHelper;
use App\Models\Setting;
use App\Models\Wallet\Wallet;
use App\Models\Wallet\WalletLog;
use App\Repositories\WalletRepository;
use App\Services\Energy\EnergyServiceDispatcher;
use App\Services\Tron\TronscanService;
use App\Services\Tron\TronService;
use App\Services\Cost\CostTracker;
use App\Services\Wallet\WalletLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class AddEnergy implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, Queueable, InteractsWithQueue;

    /**
     * Maximum time to wait for energy to be credited (seconds)
     */
    private const int MAX_WAIT_TIME = 40;

    /**
     * Energy check interval (seconds)
     */
    private const int CHECK_INTERVAL = 3;

    /**
     * @param int $id
     * @param string $addressTo
     * @param bool $forceMaxEnergy Grant the maximum ENERGY_WALLET_FOR_NEW energy (retry for exchange wallets)
     * @return void
     */
    public function __construct(readonly int $id, readonly string $addressTo = '', readonly bool $forceMaxEnergy = false)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $walletRepository = new WalletRepository;

        /**
         * @var Wallet $wallet
         */
        $wallet = $walletRepository->getById($this->id);

        $addressTo = $this->getAddressTo($wallet);

        if (!$wallet || $wallet->isTestWallet() || !$addressTo || !$wallet->amount) {
            return;
        }

        //Energy amount depends on the wallet type
        $isHasUsdtTransaction = (new TronscanService())->isHasUsdtTransactions($addressTo);
        $amountEnergy = $isHasUsdtTransaction ? Setting::getCountEnergy() : Wallet::ENERGY_WALLET_FOR_NEW;
        $tronService = new TronService();
        $walletEnergy = $tronService->getAvailableEnergy($wallet->address);

        if ($wallet->isSendTypeBlack() && !$wallet->isReturnToSameAddress()) {
            $amountEnergy = Wallet::ENERGY_WALLET_FOR_NEW; // Sweep to dex/cex
        }

        if ($wallet->isOperationSell()) {
            $amountEnergy = Wallet::ENERGY_WALLET_FOR_NEW;
        }

        // Retry: exchange wallets ran out of energy — grant the maximum
        if ($this->forceMaxEnergy) {
            $amountEnergy = Wallet::ENERGY_WALLET_FOR_NEW;
        }

        Log::channel('energy')->info('AddEnergy: Starting energy addition', [
            'id' => $wallet->id,
            'address' => $wallet->address,
            'address_to' => $addressTo,
            'isHasUsdtTransaction' => $isHasUsdtTransaction,
            'amountEnergy' => $amountEnergy,
            'forceMaxEnergy' => $this->forceMaxEnergy,
            'getCountEnergy' => Setting::getCountEnergy(),
        ]);

        //Before adding energy, check whether the wallet already has energy
        if ($walletEnergy > 0 && $walletEnergy >= $amountEnergy) {
            Log::channel('energy')->info('AddEnergy: Wallet already has sufficient energy', [
                'id' => $wallet->id,
                'address' => $wallet->address,
                'current_energy' => $walletEnergy,
                'required_energy' => $amountEnergy,
            ]);

            $walletRepository->update($wallet, [
                'amount_energy' => $walletEnergy,
                'expire_energy_at' => now()->addHours(1),
            ]);
            return;
        }

        $dispatcher = app(EnergyServiceDispatcher::class);

        $result = $dispatcher->rentEnergy(
            energyAmount: $amountEnergy,
            receiverAddress: $wallet->address,
            duration: '1h',
            wallet: $wallet
        );

        $logger = new WalletLoggerService;

        if (!$result['success']) {
            Log::channel('energy')->error('AddEnergy: Failed to rent energy', [
                'id' => $wallet->id,
                'address' => $wallet->address,
                'error' => $result['message'] ?? 'Unknown error',
                'result' => $result,
            ]);

            $logger->log(WalletLog::ADD_ENERGY_FAILED, $wallet, $result);

            $message = [
                '❌ Error adding energy' => "\n\n",
                'id' => TelegramHelper::copyTelegramStyle($wallet->id) . "\n",
                'Payment' => TelegramHelper::copyTelegramStyle($wallet->external_id_format) . "\n",
                'Wallet' => TelegramHelper::copyTelegramStyle($wallet->address) . "\n",
                'Link' => $wallet->link_view_admin . "\n",
                'Error' => ($result['message'] ?? 'Unknown error') . "\n",
            ];

            TelegramHelper::sendMessage(ArrayHelper::implodeWithKeys($message, ' '));

            return;
        }

        Log::channel('energy')->info('AddEnergy: Energy rented successfully', [
            'id' => $wallet->id,
            'address' => $wallet->address,
            'result' => $result,
        ]);

        if (isset($result['cost_trx'])) {
            CostTracker::addEnergyCost($wallet, (float) $result['cost_trx']);
        }

        // Verify that energy was actually credited
        $actualEnergy = $this->waitForEnergyCrediting($tronService, $wallet->address, $amountEnergy);

        if ($actualEnergy === false) {
            // Energy was not credited within the allotted time
            $logger->log(WalletLog::ADD_ENERGY_FAILED, $wallet, array_merge($result, [
                'error' => 'Energy was not credited within the allotted time of ' . self::MAX_WAIT_TIME . ' s'
            ]));

            Log::channel('energy')->error('AddEnergy: Energy not credited within timeout', [
                'address' => $wallet->address,
                'timeout' => self::MAX_WAIT_TIME,
                'expected_energy' => $amountEnergy,
            ]);

            $message = [
                '❌ Energy was not credited within ' . self::MAX_WAIT_TIME . ' s' => "\n\n",
                'Wallet' => $wallet->address . "\n",
                'Link' => $wallet->link_view_admin . "\n",
                'Expected' => $amountEnergy . "\n",
            ];

            return;
        }

        $energyBalance = null;
        try {
            $balanceData = $dispatcher->getBalance();
            if ($balanceData && isset($balanceData['balance'])) {
                $energyBalance = round($balanceData['balance'], 2);
            }
        } catch (\Throwable $e) {
            Log::channel('energy')->warning('AddEnergy: Failed to get balance', [
                'error' => $e->getMessage(),
            ]);
        }

        $walletRepository->update($wallet, [
            'amount_energy' => $actualEnergy,
            'energy_balance' => $energyBalance,
            'expire_energy_at' => now()->addHours(1),
        ]);

        $logger->log(WalletLog::ADD_ENERGY_SUCCESS, $wallet, array_merge($result, [
            'actual_energy' => $actualEnergy
        ]));

    }

    /**
     * Waits for energy to be credited to the wallet, polling periodically
     * @param TronService $tronService
     * @param string $address
     * @param int $expectedEnergy
     * @return int|false Actual energy amount, or false if energy was not credited
     */
    private function waitForEnergyCrediting(TronService $tronService, string $address, int $expectedEnergy)
    {
        $startTime = time();
        $attempts = 0;

        while (time() - $startTime < self::MAX_WAIT_TIME) {
            $attempts++;
            sleep(self::CHECK_INTERVAL);

            $currentEnergy = $tronService->getAvailableEnergy($address);

            Log::channel('energy')->info('AddEnergy waiting for energy', [
                'id' => $this->id,
                'address' => $address,
                'attempt' => $attempts,
                'current_energy' => $currentEnergy,
                'expected_energy' => $expectedEnergy,
                'elapsed_time' => time() - $startTime,
            ]);

            // If energy was credited (with a small tolerance)
            if ($currentEnergy >= $expectedEnergy * 0.9) {
                Log::channel('energy')->info('AddEnergy energy credited successfully', [
                    'id' => $this->id,
                    'address' => $address,
                    'attempts' => $attempts,
                    'final_energy' => $currentEnergy,
                    'total_time' => time() - $startTime,
                ]);

                return $currentEnergy;
            }
        }

        Log::channel('energy')->error('AddEnergy energy crediting timeout', [
            'id' => $this->id,
            'address' => $address,
            'attempts' => $attempts,
            'last_energy' => $tronService->getAvailableEnergy($address),
            'expected_energy' => $expectedEnergy,
            'total_time' => time() - $startTime,
        ]);

        return false;
    }

    public function uniqueId(): string
    {
        return 'energy_wallet_' . $this->id;
    }

    private function getAddressTo(Wallet $wallet): ?string
    {
        return $this->addressTo ?: $wallet->address_to;
    }
}
