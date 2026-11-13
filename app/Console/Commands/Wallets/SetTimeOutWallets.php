<?php

/**
 * php artisan wallets-set-timeout:run
 */

namespace App\Console\Commands\Wallets;

use App\Enums\WalletStatuses;
use App\Helpers\Development;
use App\Models\Wallet\Wallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetTimeOutWallets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallets-set-timeout:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeStart = microtime(true);

        $this->setTimeOut();

        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time, seconds: ' . Development::getFormatDiffMicroTime($timeStart));
    }

    private function setTimeOut(): void
    {
        DB::table('wallets')
            ->where('status', WalletStatuses::NEW->value)
            ->where('updated_at', '<=', now()->subDays(Wallet::LISTEN_DAYS))
            ->update([
                'status' => WalletStatuses::TIME_OUT->value
            ]);
    }

}
