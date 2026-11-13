<?php
/**
 * php artisan alert-check-transaction-start-wallets:run
 */

namespace App\Console\Commands\Alerts;

use App\Enums\WalletStatuses;
use App\Helpers\Development;
use App\Helpers\TelegramHelper;
use App\Models\Wallet\Transaction;
use App\Models\Wallet\TransactionAmlCheck;
use App\Models\Wallet\Wallet;
use Illuminate\Console\Command;

class AlertCheckTransactionStartWallets extends Command
{
    protected $signature = 'alert-check-transaction-start-wallets:run';

    protected $description = 'Alert for wallets stuck in the "Transaction check started" status for more than 3 minutes';

    /**
     * How many minutes a wallet may stay in the transaction check status
     */
    private const int STUCK_MINUTES = 4;

    /**
     * How many wallets to show in the message
     */
    private const int SHOW_LIMIT = 7;

    public function handle(): void
    {
        $timeStart = microtime(true);

        $this->check();

        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time (s): ' . Development::getFormatDiffMicroTime($timeStart));
    }

    private function check(): void
    {
        $query = Wallet::query()
            ->where('status', WalletStatuses::CHECK_TRANSACTION_START->value)
            ->where('updated_at', '<=', now()->subMinutes(self::STUCK_MINUTES));

        $count = (clone $query)->count();

        $this->info("Stuck wallets: {$count}");

        if ($count === 0) {
            return;
        }

        $wallets = (clone $query)
            ->with(['transactions.checks.service'])
            ->orderBy('updated_at')
            ->limit(self::SHOW_LIMIT)
            ->get();

        $lines = [];
        foreach ($wallets as $wallet) {
            $lines[] = $this->formatWallet($wallet);
        }

        if ($count > self::SHOW_LIMIT) {
            $lines[] = sprintf('… and %d more', $count - self::SHOW_LIMIT);
        }

        TelegramHelper::sendMessage(sprintf(
            "❌ Wallets stuck in the 'Transaction check started' status for more than %d min: %d\n\n%s",
            self::STUCK_MINUTES,
            $count,
            implode("\n\n", $lines),
        ));
    }

    /**
     * Wallet + AML checks for each transaction
     * @param Wallet $wallet
     * @return string
     */
    private function formatWallet(Wallet $wallet): string
    {
        $info = sprintf(
            '#%d %s — %d min',
            $wallet->id,
            TelegramHelper::copyTelegramStyle($wallet->address),
            now()->diffInMinutes($wallet->updated_at, true),
        );

        $transactions = $wallet->transactions;
        if ($transactions->isEmpty()) {
            return $info . "\n   No transactions";
        }

        foreach ($transactions as $index => $transaction) {
            $num = $index + 1;
            $amount = number_format($transaction->amount, 2, '.', ' ');

            $info .= "\n   {$num}. {$amount} USDT";
            $info .= $this->formatAmlChecks($transaction);
        }

        return $info;
    }

    /**
     * AML service statuses per transaction — shows which service did not respond
     * @param Transaction $transaction
     * @return string
     */
    private function formatAmlChecks(Transaction $transaction): string
    {
        $checks = $transaction->getLatestAmlChecks();

        if ($checks->isEmpty()) {
            return "\n      AML: not checked";
        }

        $info = "\n      AML:";
        foreach ($checks as $check) {
            /** @var TransactionAmlCheck $check */
            $serviceName = $check->service->name ?? 'Unknown service';

            $info .= "\n         {$serviceName}: " . match (true) {
                $check->isCompleted() => $check->risk_score_normalize ?? '—',
                $check->isPending() => '🔄 in progress',
                $check->isSkipped() => '⏭ skipped',
                $check->isFailed() => '⚠️ error',
                default => $check->status_name,
            };
        }

        return $info;
    }
}
