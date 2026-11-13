<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Settings;

use App\Models\Tron\TronNode;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Layouts\Rows;

class NewTronNodeFormLayout extends Rows
{
    /**
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            Input::make('newTronNode.name')
                ->title('Name')
                ->placeholder('Node 3')
                ->required(),

            Input::make('newTronNode.code')
                ->title('Code')
                ->placeholder('node_3')
                ->help('Unique node code (Latin letters)')
                ->required(),

            Select::make('newTronNode.type')
                ->title('Type')
                ->options([
                    TronNode::TYPE_OWN => 'Own node',
                    TronNode::TYPE_TRONGRID => 'TronGrid (public API)',
                ])
                ->value(TronNode::TYPE_OWN)
                ->help('Alerts compare blocks on our nodes against TronGrid'),

            Input::make('newTronNode.full_node')
                ->title('Full node')
                ->placeholder('http://0.0.0.0:8090')
                ->required(),

            Input::make('newTronNode.solidity_node')
                ->title('Solidity node')
                ->placeholder('http://0.0.0.0:8091')
                ->help('Leave empty to use the full node address'),

            Input::make('newTronNode.event_server')
                ->title('Event server')
                ->placeholder('http://0.0.0.0:18892')
                ->help('Leave empty to use the full node address'),

            Input::make('newTronNode.api_key')
                ->type('password')
                ->title('API key')
                ->autocomplete('new-password')
                ->help('Required for trongrid.io (TRON-PRO-API-KEY)'),

            Input::make('newTronNode.priority')
                ->type('number')
                ->min(1)
                ->value(3)
                ->title('Priority')
                ->help('Lower number = the node is used first'),

            Switcher::make('newTronNode.is_enabled')
                ->sendTrueOrFalse()
                ->value(true)
                ->title('Enabled'),
        ];
    }
}
