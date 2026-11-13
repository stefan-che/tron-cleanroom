<?php

/**
 * php artisan wallets:backup-keys-table
 *
 * Creates the wallet_keys_backup table in backup_db when key backup is enabled
 * (config/backup.php -> wallet_keys.enabled, env WALLET_KEY_BACKUP_ENABLED).
 */

namespace App\Console\Commands\Wallets;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BackupKeysTable extends Command
{
    protected $signature = 'wallets:backup-keys-table';

    protected $description = 'Create the wallet_keys_backup table in backup_db (if key backup is enabled)';

    public function handle(): int
    {
        if (!config('backup.wallet_keys.enabled')) {
            $this->warn('Key backup is disabled (WALLET_KEY_BACKUP_ENABLED). Table not created.');
            return self::SUCCESS;
        }

        $connection = config('backup.wallet_keys.connection');
        $table = config('backup.wallet_keys.table');

        if (Schema::connection($connection)->hasTable($table)) {
            $this->info("Table $table already exists. Skipping.");
            return self::SUCCESS;
        }

        try {
            Schema::connection($connection)->create($table, function (Blueprint $table) {
                $table->id();
                $table->string('address', 64)->unique();
                $table->text('encrypted_key');
                $table->text('secret');
                $table->text('salt');
                $table->timestamps();
            });
        } catch (Throwable $e) {
            $this->error("Failed to create table $table: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->info("Table $table created on connection $connection.");

        return self::SUCCESS;
    }
}
