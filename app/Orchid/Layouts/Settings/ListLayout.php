<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Settings;

use App\Models\Setting;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class ListLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'settings';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [

            TD::make('description', 'Name'),

            TD::make('value', 'Value')
                ->width('300')
                ->render(fn(Setting $setting) => ModalToggle::make($setting->value)
                    ->icon('arrow-right')
                    ->modal('settingsValueModal')
                    ->modalTitle('Edit setting')
                    ->method('saveSetting')
                    ->asyncParameters([
                        'setting' => $setting->id,
                    ])),
        ];
    }
}
