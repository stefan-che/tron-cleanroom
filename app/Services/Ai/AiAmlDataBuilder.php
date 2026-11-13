<?php

namespace App\Services\Ai;

use App\Http\Resources\Wallet\TransactionResource;
use App\Models\Wallet\Transaction;
use App\Models\Wallet\Wallet;
use Illuminate\Support\Facades\Log;

/**
 * Builds the payload for AI AML analysis: AML provider results per transaction
 * + extra wallet statistics (to improve report quality).
 */
class AiAmlDataBuilder
{
    /**
     * The AI report is per wallet: all current wallet transactions are sent
     * plus summary statistics.
     *
     * @param Transaction|null $current Triggering transaction (for the current_transaction block)
     * @return array{transactions: array, wallet: array}
     */
    public function build(Wallet $wallet, ?Transaction $current = null): array
    {
        $transactions = $wallet->transactions
            ->map(fn (Transaction $t) => $this->transactionData($t))
            ->values()
            ->all();

        return [
            'transactions' => $transactions,
            'wallet' => $this->walletStats($wallet, $current),
        ];
    }

    private function transactionData(Transaction $transaction): array
    {
        try {
            return TransactionResource::make($transaction)->resolve();
        } catch (\Throwable $e) {
            Log::channel('aml')->warning('AiAmlDataBuilder: failed to build TransactionResource', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'id' => $transaction->id,
                'tx_id' => $transaction->tx_id,
                'amount' => $transaction->amount,
                'from_address' => $transaction->from_address,
                'aml_risk_score' => $transaction->aml_risk_score,
            ];
        }
    }

    /**
     * Extra wallet details. Values are best-effort,
     * computed from the (incoming) transactions available in the system.
     */
    private function walletStats(Wallet $wallet, ?Transaction $current): array
    {
        $transactions = $wallet->transactions;

        $firstTx = $transactions->min('created_at');
        $lastTx = $transactions->max('created_at');

        $incomingCount = $transactions->count();
        $outgoingCount = $transactions->filter(fn (Transaction $t) => !empty($t->send_tx_hash))->count();

        $incomingVolume = (float) $transactions->sum('amount');
        $outgoingVolume = (float) $transactions->sum('last_transfer_amount');

        // Simple transit-wallet heuristic: funds arrived fast and almost all left
        $isTransit = $incomingVolume > 0
            && $outgoingVolume >= ($incomingVolume * 0.8)
            && $incomingCount > 1;

        return [
            'first_transaction' => $firstTx ? $firstTx->toIso8601String() : null,
            'last_transaction' => $lastTx ? $lastTx->toIso8601String() : null,
            'incoming_count' => $incomingCount,
            'outgoing_count' => $outgoingCount,
            'incoming_volume_usd' => round($incomingVolume, 2),
            'outgoing_volume_usd' => round($outgoingVolume, 2),
            'is_transit_wallet' => $isTransit,
            'current_transaction' => $current ? [
                'direction' => 'incoming',
                'amount' => (float) $current->amount,
                'asset' => 'USDT',
                'network' => $this->network($wallet),
            ] : null,
        ];
    }

    private function network(Wallet $wallet): string
    {
        return $wallet->isTrc20() ? 'TRC20' : 'UNKNOWN';
    }
}
