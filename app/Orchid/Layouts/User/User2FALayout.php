<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\User;

use Orchid\Platform\Models\User;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Layouts\Rows;

class User2FALayout extends Rows
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

        return [

            Group::make([
                ModalToggle::make('Scan code')
                    ->icon('barcode')
                    ->modal('User2FAModal')
                    ->modalTitle('Scan code')
                    ->method('save2Fa')
                    ->asyncParameters([
                        'user' => $user->id,
                    ]),

                Button::make('Disable')
                    ->icon('bs.trash3')
                    ->confirm('Are you sure you want to disable 2FA?')
                    ->method('remove2Fa')
                    ->canSee((bool)$user->is_two_factor_enabled),
            ]),


        ];
    }
}
