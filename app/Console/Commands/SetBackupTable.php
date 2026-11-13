<?php
/**
 * php artisan set-backup-table:run
 */

namespace App\Console\Commands;

use App\Helpers\Development;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SetBackupTable extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'set-backup-table:run';

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

        try {
            Schema::connection('backup_db')->create('wallet_keys_backup', function (Blueprint $table) {
                $table->id();
                $table->string('address', 64)->unique();
                $table->text('encrypted_key');
                $table->text('secret');
                $table->text('salt');
                $table->timestamps();
            });
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }

        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time (s): ' . Development::getFormatDiffMicroTime($timeStart));
    }
}
