<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Settings;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Layouts\Rows;

class FormValueLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return Field[]
     */
    public function fields(): array
    {
        return [

            Input::make('setting.value')
                ->type('text')
                ->max(255)
                ->required()
                ->title('Value')
                ->placeholder('Value'),
        ];
    }
}
