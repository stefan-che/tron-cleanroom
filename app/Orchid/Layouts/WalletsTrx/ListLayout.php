<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\WalletsTrx;

use App\Models\WalletTrx;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class ListLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'wallets';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('id', 'ID')
                ->width(20)
                ->render(fn(WalletTrx $wallet) => Link::make((string)$wallet->id)
                    ->rawClick()
                    ->icon('arrow-right')
                    ->route('platform.wallet_trx.edit', $wallet)
                ),
            TD::make('address', 'Address')
                ->render(fn(WalletTrx $wallet) => Link::make((string)$wallet->address)
                    ->rawClick()
                    ->icon('arrow-right')
                    ->target('_blank')
                    ->href($wallet->tronscan_address_link)
                ),
            TD::make('amount', 'TRX balance'),
            TD::make('comment', 'Description'),

            TD::make(__('Actions'))
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(fn(WalletTrx $wallet) => DropDown::make()
                    ->icon('bs.three-dots-vertical')
                    ->list([
                        Link::make(__('Edit'))
                            ->route('platform.wallet_trx.edit', $wallet->id)
                            ->icon('bs.pencil'),

                        Button::make(__('Delete'))
                            ->icon('bs.trash3')
                            ->confirm('Are you sure?')
                            ->method('remove', [
                                'id' => $wallet->id,
                            ]),
                    ])),
        ];
    }
}
