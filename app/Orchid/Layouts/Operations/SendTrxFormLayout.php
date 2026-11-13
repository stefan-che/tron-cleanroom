<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Operations;

use App\Models\Recipient\Recipient;
use App\Models\WalletTrx;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Relation;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class SendTrxFormLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            Relation::make('to')
                ->fromModel(WalletTrx::class, 'address')
                ->searchColumns('address')
                ->required()
                ->title('Wallets'),
        ];
    }
}
