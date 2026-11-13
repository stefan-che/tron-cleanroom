<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackupWalletKeyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $address;
    private array $encryptedData;

    public function __construct(string $address, array $encryptedData)
    {
        $this->address = $address;
        $this->encryptedData = $encryptedData;
    }

    public function handle(): void
    {
        if (!config('backup.wallet_keys.enabled')) {
            return;
        }

        $data = [
            'address' => sha1($this->address),
            'encrypted_key' => $this->encryptedData['key'],
            'secret' => $this->encryptedData['secret'],
            'salt' => $this->encryptedData['salt'],
            'created_at' => now(),
        ];

        try {
            DB::connection(config('backup.wallet_keys.connection'))
                ->table(config('backup.wallet_keys.table'))
                ->insert($data);
        } catch (\Exception $exception) {

            Log::channel('addr_backup')->info('Backup Failed', [
                'data' => $data,
            ]);

            Log::error('Backup Failed save DB', [
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
