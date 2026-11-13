<?php
/**
 * php artisan delete-expire-energy:run
 */

namespace App\Console\Commands;

use App\Helpers\Development;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteExpireEnergy extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete-expire-energy:run';

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

        $this->clearEnergy();

        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time (s): ' . Development::getFormatDiffMicroTime($timeStart));
    }

    private function clearEnergy(): void
    {
        do {
            $result = DB::table('wallets')
                ->where('expire_energy_at', '<=', now())
                ->limit(50)
                ->update([
                    'expire_energy_at' => null,
                    'amount_energy' => null,
                ]);

            $this->comment($result . ' done');

        } while ($result > 0);
    }

}
