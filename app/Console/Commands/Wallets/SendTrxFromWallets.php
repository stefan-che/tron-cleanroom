<?php
/**
 * php artisan send-trx-from-wallets:run TEXAMPLEADDRESS0000000000000000035 TEXAMPLEADDRESS0000000000000000036 88
 */

namespace App\Console\Commands\Wallets;

use App\Helpers\Development;
use App\Models\Wallet\WalletLog;
use App\Repositories\WalletRepository;
use App\Services\Tron\TronService;
use App\Services\Wallet\WalletLoggerService;
use Illuminate\Console\Command;

class SendTrxFromWallets extends Command
{
    // Kept on the wallet to cover the network fee (bandwidth)
    private const NETWORK_FEE_RESERVE = 1;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send-trx-from-wallets:run {from} {to} {amount}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send TRX from our wallet to the given address';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $timeStart = microtime(true);

        $result = $this->process();

        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time, seconds: ' . Development::getFormatDiffMicroTime($timeStart));

        return $result;
    }

    private function process(): int
    {
        $from = trim($this->argument('from'));
        $toAddress = trim($this->argument('to'));
        $amount = $this->argument('amount');

        if (!is_numeric($amount) || (float)$amount <= 0) {
            $this->error('Amount must be a positive number');
            return self::FAILURE;
        }
        $amount = (float)$amount;

        $tronService = new TronService();

        if (!$tronService->getNode()->isAddress($toAddress)) {
            $this->error('Invalid recipient address: ' . $toAddress);
            return self::FAILURE;
        }

        if ($from === $toAddress) {
            $this->error('Sender and recipient addresses are the same');
            return self::FAILURE;
        }

        $walletRepository = new WalletRepository();

        $fromWallet = $walletRepository->query()->where('address', $from)->first();

        if (!$fromWallet) {
            $this->error('From wallet not found');
            return self::FAILURE;
        }

        // Balance from the network: amount_trx in the DB may lag behind
        $balance = $tronService->getBalanceTrx($fromWallet->address);

        $this->table(
            ['Id', 'From', 'To', 'Balance (network)', 'Balance (DB)', 'Send Amount'],
            [
                [
                    $fromWallet->id,
                    $fromWallet->address,
                    $toAddress,
                    $balance,
                    $fromWallet->amount_trx,
                    $amount,
                ],
            ],
        );

        if ($amount > $balance - self::NETWORK_FEE_RESERVE) {
            $this->error('Insufficient TRX: maximum sendable is ' . max(0, $balance - self::NETWORK_FEE_RESERVE));
            (new WalletLoggerService)->log(WalletLog::SEND_TRX_FAILED, $fromWallet, [
                'message' => 'Not enough amount with commission to send TRX SendTrxFromWalletsCommand',
                'balance' => $balance,
                'amount' => $amount,
            ]);
            return self::FAILURE;
        }

        if (!$this->confirm('  ⚠️  Are you sure? This is a real request!')) {
            $this->line("  ℹ️  Operation cancelled");
            return self::SUCCESS;
        }

        $result = $tronService->sendTrx(
            $fromWallet->getDecPrivateKey(),
            $fromWallet->address,
            $toAddress,
            $amount
        );

        (new WalletLoggerService)->log($result['success'] ? WalletLog::SEND_TRX_SUCCESS :
            WalletLog::SEND_TRX_FAILED, $fromWallet, $result);

        if (!$result['success']) {
            $this->error('Send failed: ' . ($result['error'] ?? $result['message'] ?? 'unknown'));
            return self::FAILURE;
        }

        // The fee is known only after sending, so the remainder is estimated from the network balance
        $walletRepository->update($fromWallet, [
            'amount_trx' => $balance - $amount,
        ]);

        $this->info('Sent ' . $amount . ' TRX, txid: ' . ($result['transaction']['txid'] ?? '-'));

        return self::SUCCESS;
    }

}
