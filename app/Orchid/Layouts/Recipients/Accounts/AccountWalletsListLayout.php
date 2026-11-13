<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Recipients\Accounts;

use App\Models\Recipient\LedgerAccountWallet;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class AccountWalletsListLayout extends Table
{
    public $target = 'account_wallets';

    public function columns(): array
    {
        return [
            TD::make('wallet_address', 'Wallet address'),
            TD::make('network', 'Network'),
            TD::make('ledger_wallet_id', 'Ledger ID')
                ->render(fn(LedgerAccountWallet $w) => '<small>' . e($w->ledger_wallet_id) . '</small>'),
            TD::make('created_at', 'Added')
                ->render(fn(LedgerAccountWallet $w) => $w->created_at?->format('Y-m-d H:i')),
        ];
    }
}
