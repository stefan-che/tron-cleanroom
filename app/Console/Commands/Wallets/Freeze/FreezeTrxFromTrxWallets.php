<?php
/**
 * php artisan freeze-trx-from-trx-wallets:run TEXAMPLEADDRESS0000000000000000037 5 BANDWIDTH
 * php artisan freeze-trx-from-trx-wallets:run TEXAMPLEADDRESS0000000000000000038 30 BANDWIDTH
 */

namespace App\Console\Commands\Wallets\Freeze;

use App\Helpers\Development;
use App\Repositories\WalletTrxRepository;
use App\Services\Tron\TronService;
use Illuminate\Console\Command;

class FreezeTrxFromTrxWallets extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'freeze-trx-from-trx-wallets:run {address} {amount} {resource_type?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Freeze TRX to obtain resources (ENERGY or BANDWIDTH)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeStart = microtime(true);

        $this->process();

        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time, seconds: ' . Development::getFormatDiffMicroTime($timeStart));
    }

    private function process()
    {
        $address = $this->argument('address');
        $amount = $this->argument('amount');
        $resourceType = $this->argument('resource_type') ?? 'ENERGY';

        if (!in_array(strtoupper($resourceType), ['ENERGY', 'BANDWIDTH'])) {
            $this->error('Invalid resource type. Allowed values: ENERGY or BANDWIDTH');
            return;
        }

        $fromWallet = (new WalletTrxRepository())->getByAddress($address);

        if (!$fromWallet) {
            $this->error('Wallet not found');
            return;
        }

        $this->info("Freezing {$amount} TRX for resource {$resourceType} on wallet {$address}");

        $result = (new TronService())->freezeTrxFromTrxWallet($fromWallet, $amount, strtoupper($resourceType));

        if ($result['success']) {
            $this->info('Freeze completed successfully!');
            $this->info('Transaction ID: ' . ($result['transaction']['txID'] ?? 'Not specified'));
        } else {
            $this->error('Freeze error: ' . ($result['error'] ?? $result['message'] ?? 'Unknown error'));
        }
    }

}
