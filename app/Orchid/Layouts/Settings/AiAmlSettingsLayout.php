<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Settings;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Layouts\Rows;

class AiAmlSettingsLayout extends Rows
{
    /**
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            Switcher::make('aiSettings.enabled')
                ->sendTrueOrFalse()
                ->title('Automatic AI AML check')
                ->help('Global switch for automatic runs. The prompt and model are configured on the AI service card.'),
        ];
    }
}
