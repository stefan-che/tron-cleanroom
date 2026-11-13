<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Energy;

use App\Models\Energy\EnergyWalletHistory;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class ReplenishmentHistoryLayout extends Table
{
    public $target = 'history';

    public function columns(): array
    {
        return [
            TD::make('created_at', 'Date')
                ->render(fn(EnergyWalletHistory $h) => $h->created_at?->format('d.m.Y H:i:s')),

            TD::make('address', 'Wallet address')
                ->render(fn(EnergyWalletHistory $h) => '<code>' . ($h->walletPool?->address ?? '-') . '</code>'),

            TD::make('label', 'Label')
                ->render(fn(EnergyWalletHistory $h) => $h->walletPool?->label ?? '-'),

            TD::make('event_label', 'Event')
                ->render(fn(EnergyWalletHistory $h) => $h->event_label),

            TD::make('changed_by', 'Changed by'),

            TD::make('note', 'Note'),
        ];
    }
}
