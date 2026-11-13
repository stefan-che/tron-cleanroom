<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Settings;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Layouts\Rows;

class TronNodeFormLayout extends Rows
{
    /**
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            Input::make('tronNode.name')
                ->title('Name')
                ->required(),

            Input::make('tronNode.full_node')
                ->title('Full node')
                ->placeholder('http://0.0.0.0:8090')
                ->required(),

            Input::make('tronNode.solidity_node')
                ->title('Solidity node')
                ->placeholder('http://0.0.0.0:8091')
                ->help('Leave empty to use the full node address'),

            Input::make('tronNode.event_server')
                ->title('Event server')
                ->placeholder('http://0.0.0.0:18892')
                ->help('Leave empty to use the full node address'),

            Input::make('tronNode.api_key')
                ->type('password')
                ->title('API key')
                ->autocomplete('new-password')
                ->placeholder('••••••••')
                ->help('Required for trongrid.io (TRON-PRO-API-KEY). Leave empty to keep the current value.'),

            Input::make('tronNode.priority')
                ->type('number')
                ->min(1)
                ->title('Priority')
                ->help('Lower number = the node is used first. TronGrid must have the highest number.'),

            Switcher::make('tronNode.is_enabled')
                ->sendTrueOrFalse()
                ->title('Enabled')
                ->help('A disabled node is not used for operations and is not checked by alerts'),

            Switcher::make('tronNode.is_available')
                ->sendTrueOrFalse()
                ->title('Available')
                ->help('Set by alerts. Turn on manually to bring the node back without waiting for the next check.'),

            Input::make('tronNode.description')
                ->title('Description'),
        ];
    }
}
