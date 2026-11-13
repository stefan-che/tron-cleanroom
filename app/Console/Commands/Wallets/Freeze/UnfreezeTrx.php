<?php
/**
 * php artisan trx:unfreeze 4 ENERGY 1500
 */

namespace App\Console\Commands\Wallets\Freeze;

use App\Models\WalletTrx;
use App\Services\Tron\TronService;
use Illuminate\Console\Command;

class UnfreezeTrx extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trx:unfreeze {wallet_id} {resource_type} {amount?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unfreeze TRX (BANDWIDTH or ENERGY). IMPORTANT: unfreezing is possible only 3 days after freezing!';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $walletId = $this->argument('wallet_id');
        $resourceType = strtoupper($this->argument('resource_type'));
        $amount = $this->argument('amount');

        if (!in_array($resourceType, ['BANDWIDTH', 'ENERGY'])) {
            $this->error("Invalid resource type! Use: BANDWIDTH or ENERGY");
            return 1;
        }

        $wallet = WalletTrx::find($walletId);

        if (!$wallet) {
            $this->error("Wallet with ID {$walletId} not found!");
            return 1;
        }

        $tronService = new TronService();

        $this->info("Wallet: {$wallet->address}");

        $this->newLine();
        $this->info("📊 Currently frozen resources:");

        $accountResources = $tronService->getAccountResources($wallet->address);

        if ($resourceType === 'BANDWIDTH') {
            $bandwidth = $tronService->getAvailableBandwidth($wallet->address);
            $this->line("  Staked BANDWIDTH: {$bandwidth['staked_limit']}");
        } else {
            $energyLimit = $accountResources['EnergyLimit'] ?? 0;
            $this->line("  ENERGY: {$energyLimit}");
        }

        $this->newLine();

        if (!$amount || $amount == 0) {
            $this->warn("⚠️  No amount given - EVERYTHING will be unfrozen!");
            $amount = 0;
        } else {
            $amount = floatval($amount);
            $this->info("Will unfreeze: {$amount} TRX");
        }

        $this->newLine();
        $this->warn("⚠️  IMPORTANT: unfreezing is possible only 3 days after freezing!");
        $this->newLine();

        if (!$this->confirm("Unfreeze {$resourceType}?")) {
            $this->warn("Cancelled!");
            return 0;
        }

        $this->info("Unfreezing {$resourceType}...");

        try {
            $result = $tronService->unfreezeTrxFromTrxWallet($wallet, $amount, $resourceType);

            if ($result['success']) {
                $this->info("✅ {$resourceType} unfrozen successfully!");
                $this->newLine();

                if (isset($result['transaction']['txID'])) {
                    $this->line("Transaction ID: " . $result['transaction']['txID']);
                }

                $this->newLine();
                $this->comment("⏳ IMPORTANT: next steps:");
                $this->comment("   1. TRX is now in 'Withdrawable' status (unfrozen but not active)");
                $this->comment("   2. Wait 14 days");
                $this->comment("   3. Run: php artisan trx:withdraw-unfrozen {$wallet->id}");
                $this->comment("   4. After that the funds become active and available for use");

                $this->newLine();
                $this->info("Checking updated balance...");
                sleep(3);

                $newBalance = $tronService->getBalanceTrx($wallet->address);
                $this->info("💰 Current active balance: {$newBalance} TRX");

            } else {
                $this->error("❌ Error while unfreezing!");
                $this->error("Message: " . ($result['message'] ?? 'Unknown error'));

                if (isset($result['error'])) {
                    $errorMsg = $result['error'];
                    $this->error("Details: " . $errorMsg);

                    if (strpos($errorMsg, 'TIME_NOT_ARRIVED') !== false ||
                        strpos($errorMsg, 'time') !== false) {
                        $this->newLine();
                        $this->warn("💡 It looks like 3 days have not yet passed since freezing!");
                        $this->warn("   Try again later.");
                    }
                }
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}

