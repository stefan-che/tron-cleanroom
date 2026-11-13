<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Energy;

use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Layouts\Rows;

class WalletPoolAddFormLayout extends Rows
{
    public function fields(): array
    {
        return [
            Input::make('wallet.address')
                ->title('Wallet address (TRC-20)')
                ->placeholder('TRxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx')
                ->required(),

            Input::make('wallet.label')
                ->title('Label')
                ->placeholder('e.g. Main, Recipient 1'),

            Switcher::make('wallet.auto_replenishment_enabled')
                ->title('Auto top-up enabled')
                ->sendTrueOrFalse(),
        ];
    }
}
