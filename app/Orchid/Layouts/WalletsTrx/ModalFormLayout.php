<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\WalletsTrx;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Layouts\Rows;

class ModalFormLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return Field[]
     */
    public function fields(): array
    {
        $address = $this->query->get('address');

        return [
            Label::make()->value('Send TRX to the address:'),
            Label::make()->value($address),
        ];
    }
}
