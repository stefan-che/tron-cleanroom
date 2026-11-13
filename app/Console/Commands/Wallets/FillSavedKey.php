<?php

/**
 * php artisan wallets:fill-saved-key
 * php artisan wallets:fill-saved-key --batch=300 --pause=1
 * php artisan wallets:fill-saved-key --only-empty
 */

namespace App\Console\Commands\Wallets;

use App\Models\Wallet\Wallet;
use App\Services\Wallet\KeyManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class FillSavedKey extends Command
{
    protected $signature = 'wallets:fill-saved-key
                            {--batch=300 : Batch size}
                            {--pause=1 : Pause between batches, seconds}
                            {--only-empty : Update only wallets with an empty saved_key}';

    protected $description = 'Fill the saved_key field (decrypted private key) for wallets in batches';

    public function handle(): int
    {
        $batchSize = max(1, (int) $this->option('batch'));
        $pause = max(0, (int) $this->option('pause'));
        $onlyEmpty = (bool) $this->option('only-empty');

        $keyManager = new KeyManager();

        $query = Wallet::query()
            ->select([
                'id',
                'private_key',
                'private_key_secret',
                'private_key_salt',
                'connect_type',
                'saved_key',
            ]);

        if ($onlyEmpty) {
            $query->where(function ($q) {
                $q->whereNull('saved_key')->orWhere('saved_key', '');
            });
        }

        $total = $query->count();
        $this->info("Wallets to update: $total");

        if ($total === 0) {
            $this->warn('Nothing to update.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        $errors = 0;
        $batchIndex = 0;

        $query->chunkById($batchSize, function ($wallets) use (
            $keyManager, $bar, $pause, &$updated, &$errors, &$batchIndex
        ) {
            foreach ($wallets as $wallet) {
                /** @var Wallet $wallet */
                try {
                    $result = $keyManager->decrypt(
                        $wallet->private_key,
                        $wallet->private_key_secret,
                        $wallet->private_key_salt,
                        $wallet->connect_type,
                    );

                    $key = $result['result'] ?? '';

                    if ($key === '') {
                        $errors++;
                        $this->newLine();
                        $this->warn("Empty key after decryption, wallet id={$wallet->id}");
                        $bar->advance();
                        continue;
                    }

                    // Update only saved_key, without touching timestamps
                    Wallet::query()
                        ->where('id', $wallet->id)
                        ->update(['saved_key' => Crypt::encryptString($key)]);

                    $updated++;
                } catch (Throwable $e) {
                    $errors++;
                    $this->newLine();
                    $this->warn("Error for wallet id={$wallet->id}: {$e->getMessage()}");
                }

                $bar->advance();
            }

            $batchIndex++;

            if ($pause > 0) {
                sleep($pause);
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("Updated: $updated");

        if ($errors > 0) {
            $this->warn("Errors: $errors");
        }

        return self::SUCCESS;
    }
}
