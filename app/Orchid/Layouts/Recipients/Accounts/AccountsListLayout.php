<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Recipients\Accounts;

use App\Models\Recipient\LedgerAccount;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class AccountsListLayout extends Table
{
    public $target = 'accounts';

    public function columns(): array
    {
        return [
            TD::make('name', 'Name'),
            TD::make('currency', 'Currency'),
            TD::make('amount', 'Amount'),
            TD::make('is_show_bot', 'In bot')
                ->render(fn(LedgerAccount $a) => Button::make($a->is_show_bot ? '✅ On' : '❌ Off')
                    ->method('toggleShowBot')
                    ->parameters(['account_id' => $a->id])
                ),
            TD::make('wallets_count', 'Wallets')
                ->render(fn(LedgerAccount $a) => ModalToggle::make('List')
                    ->icon('bs.wallet2')
                    ->modal('accountWalletsModal')
                    ->asyncParameters(['account_id' => $a->id])
                ),

            TD::make('ledger_id', 'Ledger ID')
                ->defaultHidden()
                ->render(fn(LedgerAccount $a) => '<small>' . e($a->ledger_id) . '</small>'),
            TD::make('updated_at', 'Updated')
                ->render(fn(LedgerAccount $a) => $a->updated_at?->format('Y-m-d H:i')),
        ];
    }
}
