<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Recipients\Wallets;

use App\Helpers\DateHelper;
use App\Models\Recipient\Wallet;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class WalletsListLayout extends Table
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
            TD::make('id', 'Id'),
            TD::make('type_name', 'Type')
                ->render(fn(Wallet $wallet) => $wallet->type_name),

            TD::make('blockchain_type_name', 'Blockchain')
                ->render(fn(Wallet $wallet) => $wallet->blockchain_type_name),

            TD::make('address', 'Address')
                ->render(fn(Wallet $wallet) => Link::make((string)$wallet->address)
                    ->rawClick()
                    ->target('_blank')
                    ->href($wallet->tronscan_address_link)
                ),

            TD::make('updated_at', 'Updated at')
                ->render(function (Wallet $wallet) {
                    return DateHelper::toDisplayTime($wallet->updated_at)->format('Y-m-d H:i:s');
                })
        ];
    }
}
