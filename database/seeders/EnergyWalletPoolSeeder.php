<?php

/**
 * Fills the energy auto-replenishment wallet pool from recipients' TRC-20 wallets.
 * All records are created with auto_replenishment_enabled = false; enable manually as needed.
 *
 * php artisan db:seed --class=EnergyWalletPoolSeeder
 */

namespace Database\Seeders;

use App\Enums\Energy\EnergyWalletEvent;
use App\Models\Energy\EnergyWallet;
use App\Models\Recipient\Wallet as RecipientWallet;
use Illuminate\Database\Seeder;

class EnergyWalletPoolSeeder extends Seeder
{
    public function run(): void
    {
        $wallets = RecipientWallet::with('recipient')
            ->where('blockchain_type', RecipientWallet::BLOCKCHAIN_TYPE_TRC_20)
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($wallets as $recipientWallet) {
            $recipientName = $recipientWallet->recipient?->name ?? '';
            $label = $recipientName
                ? "{$recipientName} / {$recipientWallet->type_name}"
                : $recipientWallet->type_name;

            $energyWallet = EnergyWallet::withTrashed()
                ->updateOrCreate(
                    ['address' => $recipientWallet->address],
                    [
                        'recipient_wallet_id'           => $recipientWallet->id,
                        'label'                      => $label,
                        'auto_replenishment_enabled' => false,
                        'deleted_at'                 => null,
                    ]
                );

            if ($energyWallet->wasRecentlyCreated) {
                $energyWallet->addHistory(EnergyWalletEvent::ADDED, 'seeder');
                $created++;
            } else {
                $skipped++;
            }
        }

        $this->command->info("EnergyWalletPool: created {$created}, skipped (already exist) {$skipped}.");
        $this->command->warn('All wallets were added with auto-replenishment disabled. Enable it manually via the bot or the admin panel.');
    }
}
