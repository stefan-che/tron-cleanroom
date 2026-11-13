<?php
/**
 * php artisan trx:withdraw-unfrozen 4
 *
 * Withdraws unfrozen TRX (in Withdrawable status) back to the active balance
 * After unfreezing, wait 14 days, then run this command
 */

namespace App\Console\Commands\Wallets\Freeze;

use App\Models\WalletTrx;
use App\Services\Tron\TronService;
use Illuminate\Console\Command;

class WithdrawUnfrozenTrx extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trx:withdraw-unfrozen {wallet_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Withdraw unfrozen TRX (Withdrawable) back to the active balance. IMPORTANT: only 14 days after unfreezing!';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $walletId = $this->argument('wallet_id');

        $wallet = WalletTrx::find($walletId);

        if (!$wallet) {
            $this->error("Wallet with ID {$walletId} not found!");
            return 1;
        }

        $tronService = new TronService();

        $this->info("Wallet: {$wallet->address}");
        $this->newLine();

        // Check current balance
        $balance = $tronService->getBalanceTrx($wallet->address);
        $this->info("💰 Current active balance: {$balance} TRX");
        $this->newLine();

        $this->warn("⚠️  IMPORTANT:");
        $this->warn("   • This command withdraws ALL unfrozen TRX to the active balance");
        $this->warn("   • Funds can be withdrawn only 14 days after unfreezing");
        $this->warn("   • If funds are not ready for withdrawal yet, you will get an error");
        $this->newLine();

        if (!$this->confirm("Withdraw unfrozen TRX (Withdrawable) to the active balance?")) {
            $this->warn("Cancelled!");
            return 0;
        }

        $this->info("Withdrawing unfrozen funds...");

        try {
            $result = $tronService->withdrawExpireUnfreezeFromTrxWallet($wallet);

            if ($result['success']) {
                $this->info("✅ Funds withdrawn successfully!");
                $this->newLine();

                if (isset($result['transaction']['txID'])) {
                    $this->line("Transaction ID: " . $result['transaction']['txID']);
                }

                $this->info("Checking updated balance...");
                sleep(3);

                $newBalance = $tronService->getBalanceTrx($wallet->address);
                $this->info("💰 New active balance: {$newBalance} TRX");

                $balanceDiff = $newBalance - $balance;
                if ($balanceDiff > 0) {
                    $this->info("📈 Balance increase: +{$balanceDiff} TRX");
                }

            } else {
                $this->error("❌ Error while withdrawing funds!");
                $this->error("Message: " . ($result['message'] ?? 'Unknown error'));

                if (isset($result['error'])) {
                    $errorMsg = $result['error'];
                    $this->error("Details: " . $errorMsg);

                    if (stripos($errorMsg, 'no valid unfrozen') !== false ||
                        stripos($errorMsg, 'not arrived') !== false ||
                        stripos($errorMsg, 'time') !== false) {
                        $this->newLine();
                        $this->warn("💡 Possible reasons:");
                        $this->warn("   1. 14 days have not yet passed since unfreezing");
                        $this->warn("   2. No funds in Withdrawable status");
                        $this->warn("   3. Funds have already been withdrawn");
                        $this->newLine();
                        $this->comment("Hint:");
                        $this->comment("   • First: php artisan trx:unfreeze {$wallet->id} BANDWIDTH|ENERGY 1500");
                        $this->comment("   • Then wait 14 days");
                        $this->comment("   • Then: php artisan trx:withdraw-unfrozen {$wallet->id}");
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
