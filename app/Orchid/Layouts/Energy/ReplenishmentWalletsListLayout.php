<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Energy;

use App\Models\Energy\EnergyWallet;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class ReplenishmentWalletsListLayout extends Table
{
    public $target = 'wallets';

    public function columns(): array
    {
        return [
            TD::make('id', 'ID')
                ->width(60)
                ->render(fn(EnergyWallet $wallet) => Link::make((string)$wallet->id)
                    ->rawClick()
                    ->icon('arrow-right')->route('platform.energy.wallet.info', $wallet)
                ),

            TD::make('recipient_wallet_id', 'Recipient')
                ->render(fn(EnergyWallet $w) => $w->recipient?->name ?? '-'),

            TD::make('address', 'Address')
                ->render(fn(EnergyWallet $w) => $w->address),

            TD::make('label', 'Label'),

            TD::make('auto_replenishment_enabled', 'Auto top-up')
                ->render(fn(EnergyWallet $w) => $w->auto_replenishment_enabled ? '✅ Enabled' : '❌ Disabled')
                ->sort(),

            TD::make('created_at', 'Added')
                ->render(fn(EnergyWallet $w) => $w->created_at?->format('d.m.Y H:i'))
                ->sort(),

            TD::make(__('Actions'))
                ->align(TD::ALIGN_CENTER)
                ->width('160px')
                ->render(fn(EnergyWallet $w) => DropDown::make()
                    ->icon('bs.three-dots-vertical')
                    ->list([
                        Link::make('Details')
                            ->icon('bs.eye')
                            ->route('platform.energy.wallet.info', $w->id),

                        Button::make($w->auto_replenishment_enabled ? 'Disable' : 'Enable')
                            ->icon($w->auto_replenishment_enabled ? 'bs.toggle-on' : 'bs.toggle-off')
                            ->method('toggle', ['id' => $w->id]),

                        Button::make('Delete')
                            ->icon('bs.trash')
                            ->confirm('Are you sure you want to remove this wallet from the pool?')
                            ->method('remove', ['id' => $w->id]),
                    ])),
        ];
    }
}
