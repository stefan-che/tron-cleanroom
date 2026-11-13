<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\User;

use Orchid\Platform\Models\User;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\ViewField;
use Orchid\Screen\Layouts\Rows;

class User2FAModalFormLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return Field[]
     */
    public function fields(): array
    {
        /** @var User $user */
        $user = $this->query->get('user');
        $QRCode = $this->query->get('QRCode');

        return [

            ViewField::make('2fa_qr_code')
                ->value($QRCode)
                ->view('vendor.platform.fields.qr'),

            Input::make('code')
                ->title('Code from the app')
                ->help('')
                ->required(),
        ];
    }
}
