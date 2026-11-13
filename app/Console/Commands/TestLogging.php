<?php
/**
 * php artisan test:logging
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestLogging extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:logging';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Laravel log writing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting logging test...');

        // Write different log levels
        Log::debug('This is a DEBUG message - test entry');
        $this->line('✓ DEBUG log written');

        Log::info('This is an INFO message - test entry');
        $this->line('✓ INFO log written');

        Log::notice('This is a NOTICE message - test entry');
        $this->line('✓ NOTICE log written');

        Log::warning('This is a WARNING message - test entry');
        $this->line('✓ WARNING log written');

        Log::error('This is an ERROR message - test entry');
        $this->line('✓ ERROR log written');

        // Try logging an exception
        try {
            throw new \Exception('Test exception to verify logging');
        } catch (\Exception $e) {
            Log::error('Caught exception: ' . $e->getMessage(), [
                'exception' => $e,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->line('✓ EXCEPTION log written');
        }

        // Print the log file path
        $logPath = storage_path('logs/laravel.log');
        $this->info("\nCheck the log file: {$logPath}");

        // Check that the file exists
        if (file_exists($logPath)) {
            $this->info('✓ Log file exists');
            $size = filesize($logPath);
            $this->info("File size: " . number_format($size) . " bytes");

            // Show the last lines of the file
            $this->line("\n--- Last 10 log lines ---");
            $lines = file($logPath);
            $lastLines = array_slice($lines, -10);
            foreach ($lastLines as $line) {
                $this->line(trim($line));
            }
        } else {
            $this->error('✗ Log file does NOT exist!');
            $this->warn('Check permissions on the directory: ' . storage_path('logs'));

            // Check directory permissions
            $logsDir = storage_path('logs');
            if (is_writable($logsDir)) {
                $this->info('✓ logs directory is writable');
            } else {
                $this->error('✗ logs directory is NOT writable!');
                $this->warn('Run: chmod -R 775 ' . $logsDir);
            }
        }

        $this->info("\n✓ Test completed!");

        return 0;
    }
}

