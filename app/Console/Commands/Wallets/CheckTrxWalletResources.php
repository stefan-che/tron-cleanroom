<?php
/**
 * php artisan trx:check-resources 4
 */

namespace App\Console\Commands\Wallets;

use App\Models\WalletTrx;
use App\Services\Tron\TronService;
use Illuminate\Console\Command;

class CheckTrxWalletResources extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trx:check-resources {wallet_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check available resources (BANDWIDTH and ENERGY) for TRX wallets';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $walletId = $this->argument('wallet_id');

        $tronService = new TronService();

        if ($walletId) {
            // Check a specific wallet
            $wallet = WalletTrx::find($walletId);

            if (!$wallet) {
                $this->error("Wallet with ID {$walletId} not found!");
                return 1;
            }

            $this->checkWallet($wallet, $tronService);
        } else {
            // Check all active wallets
            $wallets = WalletTrx::where('status', 1)->get();

            if ($wallets->isEmpty()) {
                $this->warn('No active TRX wallets found!');
                return 0;
            }

            $this->info("Active wallets found: " . $wallets->count());
            $this->newLine();

            foreach ($wallets as $wallet) {
                $this->checkWallet($wallet, $tronService);
                $this->newLine();
            }
        }

        return 0;
    }

    private function checkWallet(WalletTrx $wallet, TronService $tronService)
    {
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("Wallet ID: {$wallet->id}");
        $this->info("Address: {$wallet->address}");

        try {
            // Balance
            $balance = $tronService->getBalanceTrx($wallet->address);
            $this->info("Balance: {$balance} TRX");

            // Fetch resources
            $bandwidth = $tronService->getAvailableBandwidth($wallet->address);
            $energy = $tronService->getAvailableEnergy($wallet->address);

            $this->newLine();
            $this->line("📊 BANDWIDTH (for TRX transactions):");
            $this->line("  Free: {$bandwidth['free']} / {$bandwidth['free_limit']}");
            $this->line("  Staked: {$bandwidth['staked']} / {$bandwidth['staked_limit']}");
            $this->line("  Total available: {$bandwidth['total']}");

            $this->newLine();
            $this->line("⚡ ENERGY (for smart contracts/USDT):");
            $accountResources = $tronService->getAccountResources($wallet->address);
            $energyLimit = $accountResources['EnergyLimit'] ?? 0;
            $this->line("  Available: {$energy} / {$energyLimit}");

            $this->newLine();

            // Recommendations
            if ($bandwidth['staked'] > 0) {
                $this->info("✅ Wallet has staked BANDWIDTH - it can send TRX transactions without fees!");
            } else {
                $this->warn("⚠️  Wallet has NO staked BANDWIDTH!");
                if ($energy > 0) {
                    $this->warn("   The wallet has only ENERGY (for USDT) but NO BANDWIDTH for TRX.");
                    $this->warn("   To activate wallets, freeze TRX for BANDWIDTH!");
                } else {
                    $this->warn("   No frozen resources. Freeze TRX for BANDWIDTH.");
                }

                if ($balance >= 1) {
                    $this->comment("   💡 You can freeze TRX for BANDWIDTH with:");
                    $this->comment("      php artisan trx:freeze-bandwidth {$wallet->id} [amount]");
                }
            }

            // Transaction estimate
            if ($bandwidth['total'] > 0) {
                $avgBandwidthPerTx = 270; // Roughly 270 bandwidth per TRX transaction
                $possibleTx = floor($bandwidth['total'] / $avgBandwidthPerTx);
                $this->info("📈 Can send ~{$possibleTx} TRX transactions without fees");
            }

        } catch (\Exception $e) {
            $this->error("Error while checking wallet: " . $e->getMessage());
        }
    }
}







