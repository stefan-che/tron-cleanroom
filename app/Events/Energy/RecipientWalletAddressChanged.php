<?php

namespace App\Events\Energy;

use App\Models\Recipient\Wallet as RecipientWallet;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RecipientWalletAddressChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly RecipientWallet $wallet,
        public readonly string $oldAddress,
        public readonly string $newAddress,
        public readonly string $changedBy = 'system',
        public readonly int $changedById = 0,
    ) {}
}
