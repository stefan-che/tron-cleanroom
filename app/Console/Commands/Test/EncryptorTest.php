<?php
/**
 * php artisan test-encryptor:run --count_test=1000
 */

namespace App\Console\Commands\Test;

use App\Helpers\Development;
use App\Helpers\StringHelper;
use App\Models\Setting;
use App\Services\Wallet\KeyManager;
use Illuminate\Console\Command;

class EncryptorTest extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test-encryptor:run {--count_test=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $timeStart = microtime(true);

        if (!$this->test()) {
            return self::FAILURE;
        }

        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time (s): ' . Development::getFormatDiffMicroTime($timeStart));

        return self::SUCCESS;
    }

    private function test(): bool
    {
        $count = (int)$this->option('count_test');
        $count = $count ?: 1000;

        $keyManager = new KeyManager();

        for ($x = 0; $x < $count; $x++) {
            $str = StringHelper::generateRandomString(random_int(10, 50), random_int(51, 100));

            $enc = $keyManager->encrypt($str, Setting::getCryptoConnect());

            $decr = $keyManager->decrypt(
                $enc['key'],
                $enc['secret'],
                $enc['salt'],
                Setting::getCryptoConnect()
            );

            if ($decr['result'] !== $str) {
                $this->error(sprintf('Decrypt mismatch at iteration %d', $x + 1));

                return false;
            }

            if (($x + 1) % 100 == 0) {
                $this->line(sprintf('Completed %d iterations successfully', $x + 1));
            }
        }

        return true;
    }

}
